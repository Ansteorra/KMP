import { Controller } from "@hotwired/stimulus";

/**
 * WorkflowVersionsController
 *
 * Handles version comparison, draft creation, and instance migration
 * on the workflow versions page.
 */
class WorkflowVersionsController extends Controller {
    static targets = ["v1", "v2", "compareBtn", "diffResults", "diffBody"]

    static values = {
        compareUrl: String,
        createDraftUrl: String,
        migrateUrl: String,
        csrf: String,
        workflowId: String,
    }

    updateCompareBtn() {
        if (!this.hasCompareBtnTarget) return;
        const v1 = this.v1Target.value;
        const v2 = this.v2Target.value;
        this.compareBtnTarget.disabled = !(v1 && v2 && v1 !== v2);
    }

    async compare() {
        const btn = this.compareBtnTarget;
        btn.disabled = true;
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        try {
            const url =
                this.compareUrlValue +
                "?v1=" + this.v1Target.value +
                "&v2=" + this.v2Target.value;
            const resp = await fetch(url, {
                headers: { Accept: "application/json" },
            });
            const diff = await resp.json();
            this._renderDiff(diff);
            this.diffResultsTarget.style.display = "";
        } catch (e) {
            console.error("Compare failed:", e);
        } finally {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }

    closeDiff() {
        this.diffResultsTarget.style.display = "none";
    }

    async createDraft() {
        if (!confirm("Create a new draft from the published version?")) return;
        const btn = event.currentTarget;
        btn.disabled = true;
        try {
            const resp = await fetch(this.createDraftUrlValue, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": this.csrfValue,
                },
                body: JSON.stringify({ workflowId: this.workflowIdValue }),
            });
            if (resp.ok) {
                window.location.reload();
            } else {
                alert("Failed to create draft.");
            }
        } catch (e) {
            alert("Error creating draft.");
        } finally {
            btn.disabled = false;
        }
    }

    async migrate(event) {
        if (!confirm("Migrate all running instances to this version?")) return;
        const btn = event.currentTarget;
        btn.disabled = true;
        try {
            const resp = await fetch(this.migrateUrlValue, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": this.csrfValue,
                },
                body: JSON.stringify({ versionId: btn.dataset.versionId }),
            });
            if (resp.ok) {
                const result = await resp.json();
                alert(result.message || "Migration complete.");
            } else {
                alert("Migration failed.");
            }
        } catch (e) {
            alert("Error during migration.");
        } finally {
            btn.disabled = false;
        }
    }

    _renderDiff(diff) {
        let html =
            '<table class="table table-sm mb-0"><thead><tr><th>Node</th><th>Change</th><th>Details</th></tr></thead><tbody>';
        if (diff.added && diff.added.length) {
            diff.added.forEach((n) => {
                html +=
                    '<tr class="table-success"><td>' +
                    this._esc(n.key || n) +
                    '</td><td><span class="badge bg-success">Added</span></td><td>' +
                    this._esc(n.type || "") +
                    "</td></tr>";
            });
        }
        if (diff.removed && diff.removed.length) {
            diff.removed.forEach((n) => {
                html +=
                    '<tr class="table-danger"><td>' +
                    this._esc(n.key || n) +
                    '</td><td><span class="badge bg-danger">Removed</span></td><td>' +
                    this._esc(n.type || "") +
                    "</td></tr>";
            });
        }
        if (diff.modified && diff.modified.length) {
            diff.modified.forEach((n) => {
                html +=
                    '<tr class="table-warning"><td>' +
                    this._esc(n.key || n) +
                    '</td><td><span class="badge bg-warning text-dark">Modified</span></td><td>' +
                    this._esc(n.changes || "") +
                    "</td></tr>";
            });
        }
        if (
            (!diff.added || !diff.added.length) &&
            (!diff.removed || !diff.removed.length) &&
            (!diff.modified || !diff.modified.length)
        ) {
            html +=
                '<tr><td colspan="3" class="text-center text-muted py-3">No differences found.</td></tr>';
        }
        html += "</tbody></table>";
        this.diffBodyTarget.innerHTML = html;
    }

    _esc(s) {
        const d = document.createElement("div");
        d.textContent = String(s);
        return d.innerHTML;
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["workflow-versions"] = WorkflowVersionsController;
