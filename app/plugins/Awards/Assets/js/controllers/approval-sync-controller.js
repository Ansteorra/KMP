import { Controller } from "@hotwired/stimulus";

/** Persistent approval synchronization progress; survives navigation and worker retries. */
class ApprovalSyncController extends Controller {
    static targets = ["status", "attention", "previous", "next", "page"];
    static values = { url: String, initial: Object };

    connect() {
        this.resultPage = 1;
        this.render(this.initialValue);
        this.refresh();
    }

    disconnect() {
        clearTimeout(this.timer);
        this.abort?.abort();
    }

    async refresh() {
        clearTimeout(this.timer);
        this.abort?.abort();
        this.abort = new AbortController();
        try {
            const url = new URL(this.urlValue, window.location.href);
            url.searchParams.set("page", this.resultPage || 1);
            const response = await fetch(url.toString(), { headers: { Accept: "application/json" }, signal: this.abort.signal });
            if (!response.ok) throw new Error("Unable to load progress. Use Refresh progress to retry.");
            this.render(await response.json());
        } catch (error) {
            if (error.name !== "AbortError") this.statusTarget.textContent = error.message;
        }
    }

    previousPage() {
        this.resultPage = Math.max(1, (this.resultPage || 1) - 1);
        this.refresh();
    }

    nextPage() {
        this.resultPage = (this.resultPage || 1) + 1;
        this.refresh();
    }

    render(run) {
        this.resultPage = run?.page || 1;
        if (this.hasPageTarget) {
            this.pageTarget.textContent = `Results page ${this.resultPage} of ${run?.pages || 1}`;
            this.previousTarget.disabled = this.resultPage <= 1;
            this.nextTarget.disabled = this.resultPage >= (run?.pages || 1);
        }
        this.attentionTarget.replaceChildren();
        if (!run?.id) {
            this.statusTarget.textContent = "No synchronization has been requested. Candidate discovery runs in the background.";
            return;
        }
        const counts = run.counts || {};
        this.statusTarget.textContent = `Synchronization #${run.id}: ${run.status.replaceAll("_", " ")}. ${run.discovering ? "Finding candidates. " : ""}${counts.restarted || 0} restarted, ${counts.pending || 0} pending, ${counts.skipped || 0} skipped, ${counts.failed || 0} failed. ${run.message || ""}`;
        for (const item of run.attention || []) {
            const li = document.createElement("li");
            li.textContent = `Recommendation #${item.recommendation_id}: ${item.status}. ${item.message || ""}`;
            this.attentionTarget.append(li);
        }
        if (["queued", "running"].includes(run.status)) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.refresh(), 5000);
        }
    }
}

window.Controllers = window.Controllers || {};
window.Controllers["awards-approval-sync"] = ApprovalSyncController;
export default ApprovalSyncController;
