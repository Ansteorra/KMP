/**
 * WorkflowHistoryManager
 *
 * Manages undo/redo snapshots of the Drawflow editor state.
 */
export default class WorkflowHistoryManager {
    /**
     * @param {number} maxHistory - Maximum number of history snapshots to keep
     */
    constructor(maxHistory = 50) {
        this._history = []
        this._historyIndex = -1
        this._locked = false
        this._maxHistory = maxHistory
    }

    /** Push current editor state onto the history stack. */
    push(editor) {
        if (this._locked || !editor) return
        const snapshot = JSON.stringify(editor.export())

        if (this._history.length > 0 && this._history[this._historyIndex] === snapshot) return

        this._history = this._history.slice(0, this._historyIndex + 1)
        this._history.push(snapshot)

        if (this._history.length > this._maxHistory) {
            this._history.shift()
        }
        this._historyIndex = this._history.length - 1
    }

    /** Undo: move back one step and restore. Returns true if state changed. */
    undo(editor) {
        if (this._historyIndex <= 0) return false
        this._historyIndex--
        this._restore(editor)
        return true
    }

    /** Redo: move forward one step and restore. Returns true if state changed. */
    redo(editor) {
        if (this._historyIndex >= this._history.length - 1) return false
        this._historyIndex++
        this._restore(editor)
        return true
    }

    /** Return a snapshot string of the current saved position (for dirty tracking). */
    getCurrentSnapshot() {
        if (this._historyIndex >= 0 && this._historyIndex < this._history.length) {
            return this._history[this._historyIndex]
        }
        return null
    }

    _restore(editor) {
        this._locked = true
        try {
            const data = JSON.parse(this._history[this._historyIndex])
            editor.import(data)
        } finally {
            this._locked = false
        }
    }
}
