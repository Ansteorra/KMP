import { Controller } from "@hotwired/stimulus";
import * as faceapi from "face-api.js";

let modelsReadyPromise = null;

class FacePhotoValidatorController extends Controller {
    static targets = ["fileInput", "warning", "submitButton"];

    static values = {
        modelBaseUrl: String,
        minWidth: { type: Number, default: 240 },
        minHeight: { type: Number, default: 240 },
        minPrimaryFaceRatio: { type: Number, default: 0.08 },
        minFaceAspectRatio: { type: Number, default: 0.72 },
        maxFaceAspectRatio: { type: Number, default: 1.35 },
        edgeMarginRatio: { type: Number, default: 0.02 },
        blurThreshold: { type: Number, default: 35 },
        maxAnalysisDimension: { type: Number, default: 640 },
        debug: { type: Boolean, default: false },
    };

    connect() {
        this.disableSubmit();
    }

    async validateFile(event) {
        const input = event?.target ?? (this.hasFileInputTarget ? this.fileInputTarget : null);
        const file = input?.files?.[0] ?? null;

        if (!file) {
            this.logDebug("No file selected");
            this.clearWarning();
            this.disableSubmit();
            this.dispatch("invalid", { detail: { message: "No file selected" } });
            return;
        }
        if (this.validationInProgress) {
            this.logDebug("Validation skipped because another validation is running");
            return;
        }

        this.validationInProgress = true;
        this.disableSubmit();
        this.showInfo("Validating face photo...");
        this.logDebug("Validating file", {
            name: file.name,
            size: file.size,
            type: file.type,
            lastModified: file.lastModified,
        });

        try {
            const imageElement = await this.loadImage(file);
            this.logDebug("Image loaded", {
                width: imageElement.naturalWidth,
                height: imageElement.naturalHeight,
            });
            this.validateImageDimensions(imageElement);
            await this.ensureModelsReady();

            const analysis = await this.analyzeFace(imageElement);
            this.logDebug("Face analysis", analysis);
            this.logDetectedFacesPreview(imageElement, analysis.faces || []);

            if (analysis.faceCount !== 1) {
                throw new Error(
                    analysis.faceCount === 0
                        ? "No face detected. Please upload a clear, front-facing photo without helmet or mask."
                        : "Multiple faces detected. Please use a photo with only one face."
                );
            }
            if (analysis.primaryFaceRatio < this.minPrimaryFaceRatioValue) {
                throw new Error("Detected face is too small in the photo. Please use a closer face photo.");
            }
            if (!analysis.primaryFaceFrontalish) {
                throw new Error("Face must be front-facing and fully visible (not side/profile).");
            }
            if (analysis.blurVariance < this.blurThresholdValue) {
                throw new Error("Photo is too blurry. Please upload a clearer image.");
            }

            this.clearWarning();
            this.enableSubmit();
            this.dispatch("valid", {
                detail: {
                    faceCount: analysis.faceCount,
                    primaryFaceRatio: analysis.primaryFaceRatio,
                    primaryFaceFrontalish: analysis.primaryFaceFrontalish,
                    blurVariance: analysis.blurVariance,
                },
            });
        } catch (error) {
            const message = error instanceof Error
                ? error.message
                : "Unable to validate photo. Please choose another image.";
            this.logDebug("Validation failed", { message, error });
            this.showError(message);
            this.disableSubmit();
            this.dispatch("invalid", { detail: { message } });
        } finally {
            this.validationInProgress = false;
        }
    }

    async ensureModelsReady() {
        if (modelsReadyPromise) {
            await modelsReadyPromise;
            return;
        }

        const modelBaseUrl = this.hasModelBaseUrlValue && this.modelBaseUrlValue
            ? this.modelBaseUrlValue
            : "/models/face-api";

        modelsReadyPromise = (async () => {
            this.logDebug("Loading face-api models", { modelBaseUrl });
            await faceapi.nets.tinyFaceDetector.loadFromUri(modelBaseUrl);
            await faceapi.nets.faceLandmark68TinyNet.loadFromUri(modelBaseUrl);
            this.logDebug("face-api models loaded");
        })();

        await modelsReadyPromise;
    }

    async analyzeFace(imageElement) {
        const analysisImage = this.createAnalysisImage(imageElement);
        const options = new faceapi.TinyFaceDetectorOptions({
            inputSize: 416,
            scoreThreshold: 0.45,
        });
        const detections = await faceapi
            .detectAllFaces(analysisImage.element, options)
            .withFaceLandmarks(true);

        const faces = detections.map((result) => {
            const box = result.detection.box;
            const scaledBox = {
                x: Math.round(box.x / analysisImage.scale),
                y: Math.round(box.y / analysisImage.scale),
                width: Math.round(box.width / analysisImage.scale),
                height: Math.round(box.height / analysisImage.scale),
            };
            const frontal = this.computeFrontalMetrics(result.landmarks, analysisImage.scale);
            const area = scaledBox.width * scaledBox.height;
            return {
                ...scaledBox,
                area,
                score: Number(result.detection.score || 0),
                frontal,
            };
        });

        const primaryFace = faces.reduce((largest, face) => {
            if (!largest || face.area > largest.area) {
                return face;
            }
            return largest;
        }, null);

        const imageArea = Math.max(1, imageElement.naturalWidth * imageElement.naturalHeight);
        const primaryFaceRatio = primaryFace ? primaryFace.area / imageArea : 0;
        const blurVariance = this.computeBlurVariance(analysisImage.element);

        return {
            faceCount: faces.length,
            faces,
            primaryFace,
            primaryFaceRatio,
            primaryFaceFrontalish: primaryFace ? this.isFaceFrontalish(primaryFace, imageElement) : false,
            blurVariance,
            analysisScale: analysisImage.scale,
            analysisWidth: analysisImage.width,
            analysisHeight: analysisImage.height,
        };
    }

    computeFrontalMetrics(landmarks, scale) {
        const leftEye = this.meanPoint(landmarks.getLeftEye(), scale);
        const rightEye = this.meanPoint(landmarks.getRightEye(), scale);
        const nose = this.meanPoint(landmarks.getNose(), scale);
        const mouth = this.meanPoint(landmarks.getMouth(), scale);
        const interEyeDistance = Math.max(1, Math.hypot(rightEye.x - leftEye.x, rightEye.y - leftEye.y));
        const yawAsymmetry = Math.abs((nose.x - leftEye.x) - (rightEye.x - nose.x)) / interEyeDistance;
        const roll = Math.abs(leftEye.y - rightEye.y) / interEyeDistance;
        const noseToMouthHorizontalOffset = Math.abs(mouth.x - nose.x) / interEyeDistance;

        return {
            yawAsymmetry,
            roll,
            noseToMouthHorizontalOffset,
            frontalish: yawAsymmetry <= 0.40 && roll <= 0.22 && noseToMouthHorizontalOffset <= 0.35,
        };
    }

    isFaceFrontalish(face, imageElement) {
        const marginX = imageElement.naturalWidth * this.edgeMarginRatioValue;
        const marginY = imageElement.naturalHeight * this.edgeMarginRatioValue;
        const aspectRatio = face.width / Math.max(face.height, 1);

        return (
            aspectRatio >= this.minFaceAspectRatioValue &&
            aspectRatio <= this.maxFaceAspectRatioValue &&
            face.x >= marginX &&
            face.y >= marginY &&
            (face.x + face.width) <= (imageElement.naturalWidth - marginX) &&
            (face.y + face.height) <= (imageElement.naturalHeight - marginY) &&
            face.frontal?.frontalish === true
        );
    }

    meanPoint(points, scale = 1) {
        if (!points || points.length === 0) {
            return { x: 0, y: 0 };
        }
        const sum = points.reduce((acc, p) => {
            acc.x += p.x;
            acc.y += p.y;
            return acc;
        }, { x: 0, y: 0 });
        return {
            x: (sum.x / points.length) / scale,
            y: (sum.y / points.length) / scale,
        };
    }

    computeBlurVariance(imageSource) {
        const width = imageSource.width || imageSource.naturalWidth || 0;
        const height = imageSource.height || imageSource.naturalHeight || 0;
        if (width < 3 || height < 3) {
            return 0;
        }

        const blurCanvas = document.createElement("canvas");
        const scale = Math.min(1, 256 / Math.max(width, height));
        blurCanvas.width = Math.max(3, Math.round(width * scale));
        blurCanvas.height = Math.max(3, Math.round(height * scale));
        const ctx = blurCanvas.getContext("2d");
        if (!ctx) {
            return 0;
        }

        ctx.drawImage(imageSource, 0, 0, blurCanvas.width, blurCanvas.height);
        const imageData = ctx.getImageData(0, 0, blurCanvas.width, blurCanvas.height).data;
        const gray = new Float32Array(blurCanvas.width * blurCanvas.height);
        for (let i = 0, p = 0; i < imageData.length; i += 4, p += 1) {
            gray[p] = (0.299 * imageData[i]) + (0.587 * imageData[i + 1]) + (0.114 * imageData[i + 2]);
        }

        let sum = 0;
        let sumSq = 0;
        let count = 0;
        const w = blurCanvas.width;
        const h = blurCanvas.height;
        for (let y = 1; y < h - 1; y += 1) {
            for (let x = 1; x < w - 1; x += 1) {
                const idx = y * w + x;
                const lap = gray[idx - w] + gray[idx - 1] - (4 * gray[idx]) + gray[idx + 1] + gray[idx + w];
                sum += lap;
                sumSq += lap * lap;
                count += 1;
            }
        }
        if (count < 1) {
            return 0;
        }
        const mean = sum / count;
        return (sumSq / count) - (mean * mean);
    }

    createAnalysisImage(imageElement) {
        const maxDimension = Math.max(1, this.maxAnalysisDimensionValue);
        const width = imageElement.naturalWidth;
        const height = imageElement.naturalHeight;
        const scale = Math.min(1, maxDimension / Math.max(width, height));

        if (scale >= 1) {
            return { element: imageElement, scale: 1, width, height };
        }

        const canvas = document.createElement("canvas");
        canvas.width = Math.max(1, Math.round(width * scale));
        canvas.height = Math.max(1, Math.round(height * scale));
        const ctx = canvas.getContext("2d");
        if (ctx) {
            ctx.drawImage(imageElement, 0, 0, canvas.width, canvas.height);
        }

        this.logDebug("Downscaled image for analysis", {
            originalWidth: width,
            originalHeight: height,
            analysisWidth: canvas.width,
            analysisHeight: canvas.height,
            scale,
        });

        return { element: canvas, scale, width: canvas.width, height: canvas.height };
    }

    validateImageDimensions(imageElement) {
        if (imageElement.naturalWidth < this.minWidthValue || imageElement.naturalHeight < this.minHeightValue) {
            throw new Error(`Photo is too small. Minimum size is ${this.minWidthValue}x${this.minHeightValue} pixels.`);
        }
    }

    loadImage(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            const image = new Image();

            image.onload = () => resolve(image);
            image.onerror = () => reject(new Error("Selected file is not a valid image."));
            reader.onerror = () => reject(new Error("Selected file could not be read."));
            reader.onload = () => {
                image.src = reader.result;
            };
            reader.readAsDataURL(file);
        });
    }

    logDetectedFacesPreview(imageElement, faces) {
        if (!this.debugValue) {
            return;
        }

        const overlayCanvas = document.createElement("canvas");
        overlayCanvas.width = imageElement.naturalWidth;
        overlayCanvas.height = imageElement.naturalHeight;
        const overlayCtx = overlayCanvas.getContext("2d");
        if (!overlayCtx) {
            return;
        }

        overlayCtx.drawImage(imageElement, 0, 0);
        overlayCtx.strokeStyle = "#ff2b2b";
        overlayCtx.lineWidth = Math.max(2, Math.round(imageElement.naturalWidth / 250));
        overlayCtx.font = `${Math.max(14, Math.round(imageElement.naturalWidth / 35))}px sans-serif`;
        overlayCtx.fillStyle = "#ff2b2b";

        faces.forEach((face, index) => {
            overlayCtx.strokeRect(face.x, face.y, face.width, face.height);
            overlayCtx.fillText(`Face ${index + 1}`, face.x + 4, Math.max(18, face.y - 6));
        });

        console.log("[face-photo-validator] Detection overlay (red boxes)", overlayCanvas);
    }

    logDebug(message, data = null) {
        if (!this.debugValue) {
            return;
        }
        if (data === null) {
            console.log("[face-photo-validator]", message);
            return;
        }
        console.log("[face-photo-validator]", message, data);
    }

    showInfo(message) {
        if (!this.hasWarningTarget) {
            return;
        }
        this.warningTarget.textContent = message;
        this.warningTarget.className = "alert alert-info mt-2 mb-0";
        this.warningTarget.classList.remove("d-none");
    }

    showError(message) {
        if (!this.hasWarningTarget) {
            alert(message);
            return;
        }
        this.warningTarget.textContent = message;
        this.warningTarget.className = "alert alert-danger mt-2 mb-0";
        this.warningTarget.classList.remove("d-none");
    }

    clearWarning() {
        if (!this.hasWarningTarget) {
            return;
        }
        this.warningTarget.textContent = "";
        this.warningTarget.className = "d-none";
    }

    disableSubmit() {
        if (!this.hasSubmitButtonTarget) {
            return;
        }
        this.submitButtonTargets.forEach((button) => {
            button.disabled = true;
        });
    }

    enableSubmit() {
        if (!this.hasSubmitButtonTarget) {
            return;
        }
        this.submitButtonTargets.forEach((button) => {
            button.disabled = false;
        });
    }

    dispatch(eventName, options = {}) {
        const event = new CustomEvent(`face-photo-validator:${eventName}`, {
            bubbles: true,
            cancelable: true,
            ...options,
        });
        this.element.dispatchEvent(event);
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["face-photo-validator"] = FacePhotoValidatorController;

export default FacePhotoValidatorController;
