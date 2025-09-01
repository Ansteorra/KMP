#!/usr/bin/env bash
set -euo pipefail

# Configuration (override via environment if desired)
: "${DB_NAME:=amp-seed}"
: "${DB_USER:=KMPSQLDEV}"
: "${DB_PASS:=P@ssw0rd}"
: "${ROOT_USER:=root}"
SQL_SOURCE_FILE="/workspaces/KMP/uat_dump.sql"
: "${DUMP_FILE:=/workspaces/KMP/dev_seed_clean.sql}"
: "${DUMP_COMPRESS:=0}"  # 1 = gzip compress, 0 = plain

echo "[INFO] Preparing clean seed database '${DB_NAME}' using dump: ${SQL_SOURCE_FILE}" >&2

if [ ! -f "${SQL_SOURCE_FILE}" ]; then
	echo "[ERROR] Source SQL file not found: ${SQL_SOURCE_FILE}" >&2
	exit 1
fi

echo "[INFO] Connecting as MySQL admin user '${DB_USER}' (you will be prompted for password)." >&2

# Build the SQL admin commands
read -r -d '' ADMIN_SQL <<EOSQL || true
DROP DATABASE IF EXISTS \`${DB_NAME}\`;
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOSQL

echo "[INFO] Dropping (if exists) and recreating database, ensuring user & privileges..." >&2
mysql -u "${DB_USER}" -p"${DB_PASS}" -e "${ADMIN_SQL}"

echo "[INFO] Creating transformed temp SQL (utf8mb4 -> utf8mb3; collation fix)..." >&2
TEMP_SQL="$(mktemp /tmp/${DB_NAME}_XXXX.sql)"
trap 'rm -f "$TEMP_SQL"' EXIT

# Perform ordered replacements: collation first, then charset
sed -e 's/utf8mb4_0900_ai_ci/utf8mb3_unicode_ci/g' \
		-e 's/utf8mb4/utf8mb3/g' "${SQL_SOURCE_FILE}" > "${TEMP_SQL}"

echo "[INFO] Importing transformed dump into ${DB_NAME} as ${DB_USER}..." >&2
mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "${TEMP_SQL}"

echo "[SUCCESS] Seed database '${DB_NAME}' recreated and populated." >&2
echo "[HINT] To verify: mysql -u ${ROOT_USER} -p -e 'USE \`${DB_NAME}\`; SHOW TABLES LIMIT 5;'" >&2

echo "[INFO] Starting data anonymization / pruning for non-demo users..." >&2

# Build deletion SQL: keep only members with last_name='Demoer' OR sca_name='Admin von Admin'
read -r -d '' CLEAN_SQL <<'EOSQL' || true
SET @keep_member_ids = (
	SELECT GROUP_CONCAT(id) FROM members WHERE last_name='Demoer' OR sca_name='Admin von Admin'
);

DELETE FROM notes;
DELETE FROM queued_jobs;

-- Safety: if no keep members found, abort
SET @count_keep = (SELECT COUNT(*) FROM members WHERE last_name='Demoer' OR sca_name='Admin von Admin');
SELECT CONCAT('[CLEANUP] Keep member count = ', @count_keep) AS info_msg;
DO CASE WHEN @count_keep = 0 THEN (SELECT 1/0) ELSE 0 END; -- force error if none

-- Temporarily disable FK checks to allow controlled manual cascade ordering
SET @old_fk = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS=0;

-- Delete from child tables referencing members (direct or via member_role / authorization) first to satisfy FKs.
DELETE aap FROM activities_authorization_approvals aap
	LEFT JOIN activities_authorizations aa ON aa.id = aap.authorization_id
	LEFT JOIN members m ON m.id = aa.member_id
WHERE m.id IS NOT NULL AND m.last_name <> 'Demoer' AND m.sca_name <> 'Admin von Admin';

DELETE aa FROM activities_authorizations aa
	JOIN members m ON m.id = aa.member_id
WHERE m.last_name <> 'Demoer' AND m.sca_name <> 'Admin von Admin';

-- Recommendations may cascade on member deletion (member_id or requester_id) causing FK error
-- because events table FK has NO ON DELETE CASCADE. So pre-delete events & state logs for ANY
-- recommendation whose member_id OR requester_id will be deleted.

DROP TEMPORARY TABLE IF EXISTS tmp_members_to_delete;
CREATE TEMPORARY TABLE tmp_members_to_delete (id INT PRIMARY KEY);
INSERT INTO tmp_members_to_delete (id)
	SELECT id FROM members WHERE last_name <> 'Demoer' AND sca_name <> 'Admin von Admin';

DROP TEMPORARY TABLE IF EXISTS tmp_recommendations_to_delete;
CREATE TEMPORARY TABLE tmp_recommendations_to_delete (id INT PRIMARY KEY);
INSERT INTO tmp_recommendations_to_delete (id)
	SELECT DISTINCT ar.id
	FROM awards_recommendations ar
	LEFT JOIN tmp_members_to_delete m1 ON m1.id = ar.member_id
	LEFT JOIN tmp_members_to_delete m2 ON m2.id = ar.requester_id
	WHERE m1.id IS NOT NULL OR m2.id IS NOT NULL;

SELECT (SELECT COUNT(*) FROM tmp_recommendations_to_delete) AS recs_to_delete,
			 (SELECT COUNT(*) FROM awards_recommendations_events ev JOIN tmp_recommendations_to_delete tr ON tr.id = ev.recommendation_id) AS rec_events_to_delete,
			 (SELECT COUNT(*) FROM awards_recommendations_states_logs sl JOIN tmp_recommendations_to_delete tr ON tr.id = sl.recommendation_id) AS rec_state_logs_to_delete;

DELETE sl FROM awards_recommendations_states_logs sl
	JOIN tmp_recommendations_to_delete tr ON tr.id = sl.recommendation_id;

DELETE ev FROM awards_recommendations_events ev
	JOIN tmp_recommendations_to_delete tr ON tr.id = ev.recommendation_id;

DELETE ar FROM awards_recommendations ar
	JOIN tmp_recommendations_to_delete tr ON tr.id = ar.id;

DELETE wrapp FROM warrant_roster_approvals wrapp
	JOIN members m ON m.id = wrapp.approver_id
WHERE m.last_name <> 'Demoer' AND m.sca_name <> 'Admin von Admin';

DELETE w FROM warrants w
	JOIN members m ON m.id = w.member_id
WHERE m.last_name <> 'Demoer' AND m.sca_name <> 'Admin von Admin';

-- Additional warrants whose member_role_id points to a role owned by a soon-to-be-deleted member (but warrant.member_id may be a kept member)
DELETE w2 FROM warrants w2
	JOIN member_roles mr ON mr.id = w2.member_role_id
	JOIN members mdel ON mdel.id = mr.member_id
WHERE mdel.last_name <> 'Demoer' AND mdel.sca_name <> 'Admin von Admin';

DELETE wo FROM officers_officers wo
	JOIN members m ON m.id = wo.member_id
WHERE m.last_name <> 'Demoer' AND m.sca_name <> 'Admin von Admin';

DELETE mr FROM member_roles mr
	JOIN members m ON m.id = mr.member_id
WHERE m.last_name <> 'Demoer' AND m.sca_name <> 'Admin von Admin';

-- Diagnostics & reinforced cleanup for warrants referencing member_roles of deleting members
SELECT COUNT(*) AS warrants_linked_to_deleting_roles_pre FROM warrants w
	JOIN member_roles mr ON mr.id = w.member_role_id
	JOIN members mdel ON mdel.id = mr.member_id
	WHERE mdel.last_name <> 'Demoer' AND mdel.sca_name <> 'Admin von Admin';

-- Second pass explicit delete (defensive)
DELETE FROM warrants
	WHERE member_role_id IN (
		SELECT mr.id FROM member_roles mr
			JOIN members mdel ON mdel.id = mr.member_id
			WHERE mdel.last_name <> 'Demoer' AND mdel.sca_name <> 'Admin von Admin'
	);

SELECT COUNT(*) AS warrants_linked_to_deleting_roles_post FROM warrants w
	JOIN member_roles mr ON mr.id = w.member_role_id
	JOIN members mdel ON mdel.id = mr.member_id
	WHERE mdel.last_name <> 'Demoer' AND mdel.sca_name <> 'Admin von Admin';

-- Abort if any still remain (should be zero now)
SET @left_warrants := (
	SELECT COUNT(*) FROM warrants w
		JOIN member_roles mr ON mr.id = w.member_role_id
		JOIN members mdel ON mdel.id = mr.member_id
		WHERE mdel.last_name <> 'Demoer' AND mdel.sca_name <> 'Admin von Admin'
);
DO CASE WHEN @left_warrants > 0 THEN (SELECT 1/0) ELSE 0 END;

DELETE w
FROM warrant_roster_approvals w
WHERE
    w.warrant_roster_id in (
        select id
        from warrant_rosters
        where
            id not in(
                select warrant_roster_id
                from warrants
            )
    );

DELETE from warrant_rosters
where
    id not in(
        select warrant_roster_id
        from warrants
    );

-- Finally delete members themselves
DELETE FROM members WHERE last_name <> 'Demoer' AND sca_name <> 'Admin von Admin';

-- Clear all member passwords (set to empty string for seed data)
UPDATE members SET password='';

-- Re-enable FK checks
SET FOREIGN_KEY_CHECKS=@old_fk;

SELECT 'CLEANUP_COMPLETE' AS status,
	(SELECT COUNT(*) FROM members) AS remaining_members,
	(SELECT COUNT(*) FROM awards_recommendations) AS remaining_recommendations,
	(SELECT COUNT(*) FROM awards_recommendations_events) AS remaining_recommendation_events;
EOSQL

if [ "${DRY_RUN:-0}" = "1" ]; then
	echo "[DRY_RUN] Showing counts that would be affected (approx)." >&2
	mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "SELECT COUNT(*) AS total_members_before FROM members; SELECT COUNT(*) AS deletable_members FROM members WHERE last_name <> 'Demoer' AND sca_name <> 'Admin von Admin';"
	echo "[DRY_RUN] To execute cleanup set DRY_RUN=0 (or unset) and rerun script." >&2
else
	echo "[INFO] Executing cleanup deletions..." >&2
	mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "${CLEAN_SQL}" || { echo '[ERROR] Cleanup failed.' >&2; exit 1; }
	echo "[INFO] Normalizing actor reference columns (created_by/updated_by/approved_by/approver_id) to 1..." >&2
	actor_updates=$(mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -N <<'EOSQL'
SELECT CONCAT('UPDATE `',TABLE_NAME,'` SET `',COLUMN_NAME,'`=1 WHERE `',COLUMN_NAME,'` IS NOT NULL AND `',COLUMN_NAME,'`<>1;')
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME IN ('created_by','updated_by','approved_by','approver_id');
EOSQL
)
	if [ -n "${actor_updates}" ]; then
		mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "${actor_updates}" || { echo '[ERROR] Failed normalizing actor columns.' >&2; exit 1; }
	else
		echo "[INFO] No actor reference columns found to normalize." >&2
	fi
	echo "[INFO] Cleanup complete." >&2
	echo "[INFO] Dumping cleaned database to ${DUMP_FILE}${DUMP_COMPRESS:+ (compressed)}" >&2
	if [ "${DUMP_COMPRESS}" = "1" ]; then
		mysqldump -u "${DB_USER}" -p"${DB_PASS}" --routines --triggers --single-transaction --skip-lock-tables "${DB_NAME}" | gzip -9 > "${DUMP_FILE}.gz"
		echo "[INFO] Dump written: ${DUMP_FILE}.gz" >&2
	else
		mysqldump -u "${DB_USER}" -p"${DB_PASS}" --routines --triggers --single-transaction --skip-lock-tables "${DB_NAME}" > "${DUMP_FILE}"
		echo "[INFO] Dump written: ${DUMP_FILE}" >&2
	fi
fi