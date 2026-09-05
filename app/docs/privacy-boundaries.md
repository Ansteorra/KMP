# Privacy boundaries

Member record access and member PII access are separate policy decisions. API details and
staff contact lookup require `viewPii` on the target member. API private-field search applies
the same policy scope before matching; a search must not reveal hidden email or legal-name
values through result counts. Member grid rows are individually projected. Scoped PII grants
allow permitted rows to display private fields, but private sorting/filtering/searching in
that mixed grid requires a global PII grant. Record detail remains available through its
normal authorization. Staff forms continue accepting contact details provided for that role.

Attendance enrichment uses `GatheringAttendancePolicy::canViewShared`: self/guardian access,
consent to the hosting group plus recipient attendance permission, same-kingdom sharing, or
an explicit scoped crown-recipient grant. Awards maps existing crown-field permissions to
that recipient grant. Hidden attendance must be indistinguishable from no attendance in
public nomination helpers, including indicator flags. Recommendation visibility alone is
not consent to see a member's crown-only attendance.

Nomination HTTP input and workflow submission both use a scalar field allowlist. Creation
accepts existing gathering IDs only; it cannot create or edit associated gatherings. Requester
identity and audit/state fields are server-owned. A public requester name is unverified text,
and public submissions cannot claim an existing member's requester identity. Custom workflows
must use the supported creation fields and ID links, not nested association payloads.

PDF parsing runs in a separate PHP process with 256 MB memory and a 30-second wall-time limit.
Each input is limited to 50 MB; a merge accepts at most 100 inputs, 100 MB total, and 500 pages.
Every file must validate. Unsupported compression, missing files, invalid PDF content, and
limit exhaustion fail the whole conversion; no partial upload is reported as successful.
Optional browser thumbnails are bounded, decoded, and re-encoded before storage. The process
boundary limits parser resource use; it is not a separate operating-system security sandbox.

Application Insights, OTLP, and local file log engines sanitize context before interpolation.
Waiver notes, entity dumps, document bytes, raw tokens, and private query values do not belong
in diagnostics. Use fixed event names, correlation IDs, counts, and timings. Request targets
omit query strings and record/token segments; Apache records method/status/timing without raw
request targets or referers. Existing log files and previously exported telemetry require
operator access/retention review; these changes do not erase historical data.
