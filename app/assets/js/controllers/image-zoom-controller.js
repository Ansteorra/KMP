import { Controller } from "@hotwired/stimulus";

/**
 * Zoomable/pannable image controller for modal dialogs.
 *
 * Supports mouse-wheel zoom, click-drag pan, and touch pinch-zoom.
 * Double-click resets to fit view.
 *
 * Targets:
 * - image: The <img> element to make zoomable
 *
 * Values:
 * - minScale: minimum zoom (default 1)
 * - maxScale: maximum zoom (default 8)
 */
class ImageZoom extends Controller {
    static targets = ["image"];

    static values = {
        minScale: { type: Number, default: 1 },
        maxScale: { type: Number, default: 8 },
    };

    connect() {
        this.scale = 1;
        this.translateX = 0;
        this.translateY = 0;
        this.dragging = false;
        this.lastX = 0;
        this.lastY = 0;
        this.initialPinchDistance = null;
        this.initialPinchScale = 1;

        const container = this.element;
        container.style.overflow = "hidden";
        container.style.cursor = "grab";
        container.style.touchAction = "none";

        const img = this.imageTarget;
        img.style.transformOrigin = "0 0";
        img.style.transition = "none";
        img.style.userSelect = "none";
        img.style.webkitUserSelect = "none";
        img.draggable = false;

        this._onWheel = this._onWheel.bind(this);
        this._onPointerDown = this._onPointerDown.bind(this);
        this._onPointerMove = this._onPointerMove.bind(this);
        this._onPointerUp = this._onPointerUp.bind(this);
        this._onDblClick = this._onDblClick.bind(this);
        this._onTouchStart = this._onTouchStart.bind(this);
        this._onTouchMove = this._onTouchMove.bind(this);
        this._onTouchEnd = this._onTouchEnd.bind(this);

        container.addEventListener("wheel", this._onWheel, { passive: false });
        container.addEventListener("pointerdown", this._onPointerDown);
        container.addEventListener("pointermove", this._onPointerMove);
        container.addEventListener("pointerup", this._onPointerUp);
        container.addEventListener("pointerleave", this._onPointerUp);
        container.addEventListener("dblclick", this._onDblClick);
        container.addEventListener("touchstart", this._onTouchStart, { passive: false });
        container.addEventListener("touchmove", this._onTouchMove, { passive: false });
        container.addEventListener("touchend", this._onTouchEnd);

        this._applyTransform();
    }

    disconnect() {
        const container = this.element;
        container.removeEventListener("wheel", this._onWheel);
        container.removeEventListener("pointerdown", this._onPointerDown);
        container.removeEventListener("pointermove", this._onPointerMove);
        container.removeEventListener("pointerup", this._onPointerUp);
        container.removeEventListener("pointerleave", this._onPointerUp);
        container.removeEventListener("dblclick", this._onDblClick);
        container.removeEventListener("touchstart", this._onTouchStart);
        container.removeEventListener("touchmove", this._onTouchMove);
        container.removeEventListener("touchend", this._onTouchEnd);
    }

    _onWheel(e) {
        e.preventDefault();
        const rect = this.element.getBoundingClientRect();
        const cursorX = e.clientX - rect.left;
        const cursorY = e.clientY - rect.top;

        const delta = e.deltaY > 0 ? 0.9 : 1.1;
        this._zoomAt(cursorX, cursorY, delta);
    }

    _onPointerDown(e) {
        if (e.pointerType === "touch") return;
        this.dragging = true;
        this.lastX = e.clientX;
        this.lastY = e.clientY;
        this.element.style.cursor = "grabbing";
        this.element.setPointerCapture(e.pointerId);
    }

    _onPointerMove(e) {
        if (!this.dragging || e.pointerType === "touch") return;
        const dx = e.clientX - this.lastX;
        const dy = e.clientY - this.lastY;
        this.lastX = e.clientX;
        this.lastY = e.clientY;
        this.translateX += dx;
        this.translateY += dy;
        this._clampTranslation();
        this._applyTransform();
    }

    _onPointerUp(e) {
        if (e.pointerType === "touch") return;
        this.dragging = false;
        this.element.style.cursor = "grab";
    }

    _onDblClick() {
        this.scale = 1;
        this.translateX = 0;
        this.translateY = 0;
        this._applyTransform();
    }

    // Touch pinch-zoom
    _onTouchStart(e) {
        if (e.touches.length === 2) {
            e.preventDefault();
            this.initialPinchDistance = this._pinchDistance(e.touches);
            this.initialPinchScale = this.scale;
        } else if (e.touches.length === 1 && this.scale > 1) {
            e.preventDefault();
            this.dragging = true;
            this.lastX = e.touches[0].clientX;
            this.lastY = e.touches[0].clientY;
        }
    }

    _onTouchMove(e) {
        if (e.touches.length === 2 && this.initialPinchDistance) {
            e.preventDefault();
            const dist = this._pinchDistance(e.touches);
            const ratio = dist / this.initialPinchDistance;
            const newScale = Math.min(
                this.maxScaleValue,
                Math.max(this.minScaleValue, this.initialPinchScale * ratio)
            );

            const rect = this.element.getBoundingClientRect();
            const cx = ((e.touches[0].clientX + e.touches[1].clientX) / 2) - rect.left;
            const cy = ((e.touches[0].clientY + e.touches[1].clientY) / 2) - rect.top;

            const scaleRatio = newScale / this.scale;
            this.translateX = cx - scaleRatio * (cx - this.translateX);
            this.translateY = cy - scaleRatio * (cy - this.translateY);
            this.scale = newScale;
            this._clampTranslation();
            this._applyTransform();
        } else if (e.touches.length === 1 && this.dragging) {
            e.preventDefault();
            const dx = e.touches[0].clientX - this.lastX;
            const dy = e.touches[0].clientY - this.lastY;
            this.lastX = e.touches[0].clientX;
            this.lastY = e.touches[0].clientY;
            this.translateX += dx;
            this.translateY += dy;
            this._clampTranslation();
            this._applyTransform();
        }
    }

    _onTouchEnd(e) {
        if (e.touches.length < 2) {
            this.initialPinchDistance = null;
        }
        if (e.touches.length === 0) {
            this.dragging = false;
        }
    }

    _pinchDistance(touches) {
        const dx = touches[0].clientX - touches[1].clientX;
        const dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    _zoomAt(cx, cy, factor) {
        const newScale = Math.min(
            this.maxScaleValue,
            Math.max(this.minScaleValue, this.scale * factor)
        );
        const ratio = newScale / this.scale;
        this.translateX = cx - ratio * (cx - this.translateX);
        this.translateY = cy - ratio * (cy - this.translateY);
        this.scale = newScale;
        this._clampTranslation();
        this._applyTransform();
    }

    _clampTranslation() {
        if (this.scale <= 1) {
            this.translateX = 0;
            this.translateY = 0;
            return;
        }
        const img = this.imageTarget;
        const container = this.element;
        const cw = container.clientWidth;
        const ch = container.clientHeight;

        // Scaled image dimensions based on rendered size
        const renderedW = img.clientWidth * this.scale;
        const renderedH = img.clientHeight * this.scale;

        const minX = Math.min(0, cw - renderedW);
        const minY = Math.min(0, ch - renderedH);
        this.translateX = Math.max(minX, Math.min(0, this.translateX));
        this.translateY = Math.max(minY, Math.min(0, this.translateY));
    }

    _applyTransform() {
        this.imageTarget.style.transform =
            `translate(${this.translateX}px, ${this.translateY}px) scale(${this.scale})`;
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["image-zoom"] = ImageZoom;
