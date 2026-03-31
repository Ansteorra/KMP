jest.mock('face-api.js', () => ({
  nets: {
    tinyFaceDetector: { loadFromUri: jest.fn().mockResolvedValue() },
    faceLandmark68TinyNet: { loadFromUri: jest.fn().mockResolvedValue() },
  },
  TinyFaceDetectorOptions: function TinyFaceDetectorOptions() {},
  detectAllFaces: jest.fn(),
}));

import FacePhotoValidatorController from '../../../assets/js/controllers/face-photo-validator-controller.js';

describe('FacePhotoValidatorController', () => {
  let controller;

  beforeEach(() => {
    controller = new FacePhotoValidatorController();
    controller.element = document.createElement('div');
    controller.warningTarget = document.createElement('div');
    controller.submitButtonTargets = [{ disabled: false }];
    controller.hasWarningTarget = true;
    controller.hasSubmitButtonTarget = true;
    controller.minWidthValue = 240;
    controller.minHeightValue = 240;
    controller.blurThresholdValue = 35;
    controller.clearWarning = jest.fn();
    controller.enableSubmit = jest.fn();
    controller.disableSubmit = jest.fn();
    controller.showInfo = jest.fn();
    controller.showError = jest.fn();
    controller.dispatch = jest.fn();
  });

  test('keeps submit disabled when no file is selected', async () => {
    await controller.validateFile({ target: { files: [] } });

    expect(controller.clearWarning).toHaveBeenCalled();
    expect(controller.disableSubmit).toHaveBeenCalled();
    expect(controller.dispatch).toHaveBeenCalledWith('invalid', expect.any(Object));
  });

  test('accepts a valid single-face photo', async () => {
    controller.loadImage = jest.fn().mockResolvedValue({ naturalWidth: 400, naturalHeight: 400 });
    controller.getOpenCv = jest.fn().mockResolvedValue({});
    controller.ensureCascadeLoaded = jest.fn().mockResolvedValue();
    controller.ensureModelsReady = jest.fn().mockResolvedValue();
    controller.analyzeFace = jest.fn().mockResolvedValue({
      faceCount: 1,
      primaryFaceRatio: 0.2,
      primaryFaceFrontalish: true,
      blurVariance: 220
    });
    const file = new File(['img'], 'selfie.jpg', { type: 'image/jpeg' });

    await controller.validateFile({ target: { files: [file] } });

    expect(controller.disableSubmit).toHaveBeenCalled();
    expect(controller.clearWarning).toHaveBeenCalled();
    expect(controller.enableSubmit).toHaveBeenCalled();
    expect(controller.showError).not.toHaveBeenCalled();
  });

  test('rejects images with no face', async () => {
    controller.loadImage = jest.fn().mockResolvedValue({ naturalWidth: 400, naturalHeight: 400 });
    controller.ensureModelsReady = jest.fn().mockResolvedValue();
    controller.analyzeFace = jest.fn().mockResolvedValue({
      faceCount: 0,
      primaryFaceRatio: 0,
      primaryFaceFrontalish: false,
      blurVariance: 220
    });
    const file = new File(['img'], 'noface.jpg', { type: 'image/jpeg' });

    await controller.validateFile({ target: { files: [file] } });

    expect(controller.showError).toHaveBeenCalled();
    expect(controller.dispatch).toHaveBeenCalledWith('invalid', expect.any(Object));
    expect(controller.enableSubmit).not.toHaveBeenCalled();
  });

  test('queues latest file while validation is in progress', async () => {
    let resolveFirstImage;
    const firstImagePromise = new Promise((resolve) => {
      resolveFirstImage = resolve;
    });
    controller.loadImage = jest.fn((file) => (
      file.name === 'first.jpg'
        ? firstImagePromise
        : Promise.resolve({ naturalWidth: 400, naturalHeight: 400 })
    ));
    controller.ensureModelsReady = jest.fn().mockResolvedValue();
    controller.analyzeFace = jest.fn().mockResolvedValue({
      faceCount: 1,
      primaryFaceRatio: 0.2,
      primaryFaceFrontalish: true,
      blurVariance: 220
    });

    const firstFile = new File(['img1'], 'first.jpg', { type: 'image/jpeg' });
    const secondFile = new File(['img2'], 'second.jpg', { type: 'image/jpeg' });
    const input = { files: [firstFile] };

    const firstRun = controller.validateFile({ target: input });
    await Promise.resolve();

    input.files = [secondFile];
    await controller.validateFile({ target: input });
    expect(controller.loadImage).toHaveBeenCalledTimes(1);

    resolveFirstImage({ naturalWidth: 400, naturalHeight: 400 });
    await firstRun;
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(controller.loadImage).toHaveBeenCalledTimes(2);
    expect(controller.loadImage.mock.calls[1][0]).toBe(secondFile);
  });
});

// ---------------------------------------------------------------------------
// NEW TESTS – internal methods & additional validateFile rejection paths
// ---------------------------------------------------------------------------

describe('FacePhotoValidatorController internal methods', () => {
  let controller;

  beforeEach(() => {
    controller = new FacePhotoValidatorController();
    controller.element = document.createElement('div');

    // Targets
    controller.warningTarget = document.createElement('div');
    controller.submitButtonTargets = [{ disabled: false }, { disabled: false }];
    controller.hasWarningTarget = true;
    controller.hasSubmitButtonTarget = true;

    // Values (mirror the static defaults)
    controller.minWidthValue = 240;
    controller.minHeightValue = 240;
    controller.minPrimaryFaceRatioValue = 0.08;
    controller.minFaceAspectRatioValue = 0.72;
    controller.maxFaceAspectRatioValue = 1.35;
    controller.edgeMarginRatioValue = 0.02;
    controller.blurThresholdValue = 35;
    controller.maxAnalysisDimensionValue = 640;
    controller.debugValue = false;
  });

  // ---- validateImageDimensions -------------------------------------------
  describe('validateImageDimensions', () => {
    test('passes for image meeting minimum dimensions', () => {
      const img = { naturalWidth: 400, naturalHeight: 400 };
      expect(() => controller.validateImageDimensions(img)).not.toThrow();
    });

    test('passes for image exactly at minimum dimensions', () => {
      const img = { naturalWidth: 240, naturalHeight: 240 };
      expect(() => controller.validateImageDimensions(img)).not.toThrow();
    });

    test('throws when width is too small', () => {
      const img = { naturalWidth: 100, naturalHeight: 400 };
      expect(() => controller.validateImageDimensions(img)).toThrow(/too small/i);
    });

    test('throws when height is too small', () => {
      const img = { naturalWidth: 400, naturalHeight: 100 };
      expect(() => controller.validateImageDimensions(img)).toThrow(/too small/i);
    });

    test('throws when both dimensions are too small', () => {
      const img = { naturalWidth: 10, naturalHeight: 10 };
      expect(() => controller.validateImageDimensions(img)).toThrow(/too small/i);
    });

    test('error message contains minimum dimensions', () => {
      const img = { naturalWidth: 10, naturalHeight: 10 };
      expect(() => controller.validateImageDimensions(img)).toThrow('240x240');
    });
  });

  // ---- meanPoint ---------------------------------------------------------
  describe('meanPoint', () => {
    test('computes mean of points with scale 1', () => {
      const points = [{ x: 2, y: 4 }, { x: 4, y: 6 }];
      expect(controller.meanPoint(points, 1)).toEqual({ x: 3, y: 5 });
    });

    test('applies scale divisor', () => {
      const points = [{ x: 10, y: 20 }];
      expect(controller.meanPoint(points, 2)).toEqual({ x: 5, y: 10 });
    });

    test('returns {0,0} for empty array', () => {
      expect(controller.meanPoint([], 1)).toEqual({ x: 0, y: 0 });
    });

    test('returns {0,0} for null input', () => {
      expect(controller.meanPoint(null, 1)).toEqual({ x: 0, y: 0 });
    });

    test('returns {0,0} for undefined input', () => {
      expect(controller.meanPoint(undefined, 1)).toEqual({ x: 0, y: 0 });
    });

    test('defaults scale to 1 when omitted', () => {
      const points = [{ x: 6, y: 8 }];
      expect(controller.meanPoint(points)).toEqual({ x: 6, y: 8 });
    });

    test('averages multiple points correctly', () => {
      const points = [{ x: 0, y: 0 }, { x: 10, y: 0 }, { x: 0, y: 10 }, { x: 10, y: 10 }];
      expect(controller.meanPoint(points, 1)).toEqual({ x: 5, y: 5 });
    });
  });

  // ---- computeFrontalMetrics --------------------------------------------
  describe('computeFrontalMetrics', () => {
    function makeLandmarks(leftEye, rightEye, nose, mouth) {
      return {
        getLeftEye: () => leftEye,
        getRightEye: () => rightEye,
        getNose: () => nose,
        getMouth: () => mouth,
      };
    }

    test('frontal face returns frontalish true', () => {
      const landmarks = makeLandmarks(
        [{ x: 30, y: 50 }],
        [{ x: 70, y: 50 }],
        [{ x: 50, y: 70 }],
        [{ x: 50, y: 90 }],
      );
      const result = controller.computeFrontalMetrics(landmarks, 1);
      expect(result.frontalish).toBe(true);
      expect(result.yawAsymmetry).toBeCloseTo(0, 5);
      expect(result.roll).toBeCloseTo(0, 5);
      expect(result.noseToMouthHorizontalOffset).toBeCloseTo(0, 5);
    });

    test('turned face (high yaw) returns frontalish false', () => {
      const landmarks = makeLandmarks(
        [{ x: 20, y: 50 }],
        [{ x: 80, y: 50 }],
        [{ x: 25, y: 70 }],
        [{ x: 50, y: 90 }],
      );
      const result = controller.computeFrontalMetrics(landmarks, 1);
      expect(result.frontalish).toBe(false);
      expect(result.yawAsymmetry).toBeGreaterThan(0.40);
    });

    test('tilted face (high roll) returns frontalish false', () => {
      const landmarks = makeLandmarks(
        [{ x: 30, y: 30 }],
        [{ x: 70, y: 60 }],
        [{ x: 50, y: 70 }],
        [{ x: 50, y: 90 }],
      );
      const result = controller.computeFrontalMetrics(landmarks, 1);
      expect(result.frontalish).toBe(false);
      expect(result.roll).toBeGreaterThan(0.22);
    });

    test('mouth offset too high returns frontalish false', () => {
      const landmarks = makeLandmarks(
        [{ x: 30, y: 50 }],
        [{ x: 70, y: 50 }],
        [{ x: 50, y: 70 }],
        [{ x: 80, y: 90 }],
      );
      const result = controller.computeFrontalMetrics(landmarks, 1);
      expect(result.frontalish).toBe(false);
      expect(result.noseToMouthHorizontalOffset).toBeGreaterThan(0.35);
    });

    test('respects scale parameter', () => {
      const landmarks = makeLandmarks(
        [{ x: 60, y: 100 }],
        [{ x: 140, y: 100 }],
        [{ x: 100, y: 140 }],
        [{ x: 100, y: 180 }],
      );
      const result = controller.computeFrontalMetrics(landmarks, 2);
      expect(result.frontalish).toBe(true);
    });
  });

  // ---- isFaceFrontalish --------------------------------------------------
  describe('isFaceFrontalish', () => {
    const imageElement = { naturalWidth: 400, naturalHeight: 400 };

    test('returns true for valid frontal face', () => {
      const face = {
        x: 100, y: 100, width: 120, height: 140,
        frontal: { frontalish: true },
      };
      expect(controller.isFaceFrontalish(face, imageElement)).toBe(true);
    });

    test('returns false when aspect ratio is too low', () => {
      const face = {
        x: 100, y: 100, width: 50, height: 200,
        frontal: { frontalish: true },
      };
      expect(controller.isFaceFrontalish(face, imageElement)).toBe(false);
    });

    test('returns false when aspect ratio is too high', () => {
      const face = {
        x: 100, y: 100, width: 200, height: 50,
        frontal: { frontalish: true },
      };
      expect(controller.isFaceFrontalish(face, imageElement)).toBe(false);
    });

    test('returns false when face is at left edge', () => {
      const face = {
        x: 0, y: 100, width: 120, height: 140,
        frontal: { frontalish: true },
      };
      expect(controller.isFaceFrontalish(face, imageElement)).toBe(false);
    });

    test('returns false when face is at top edge', () => {
      const face = {
        x: 100, y: 0, width: 120, height: 140,
        frontal: { frontalish: true },
      };
      expect(controller.isFaceFrontalish(face, imageElement)).toBe(false);
    });

    test('returns false when face extends past right edge', () => {
      const face = {
        x: 300, y: 100, width: 120, height: 140,
        frontal: { frontalish: true },
      };
      expect(controller.isFaceFrontalish(face, imageElement)).toBe(false);
    });

    test('returns false when face extends past bottom edge', () => {
      const face = {
        x: 100, y: 280, width: 120, height: 140,
        frontal: { frontalish: true },
      };
      expect(controller.isFaceFrontalish(face, imageElement)).toBe(false);
    });

    test('returns false when frontal data is missing', () => {
      const face = { x: 100, y: 100, width: 120, height: 140 };
      expect(controller.isFaceFrontalish(face, imageElement)).toBe(false);
    });

    test('returns false when frontal.frontalish is false', () => {
      const face = {
        x: 100, y: 100, width: 120, height: 140,
        frontal: { frontalish: false },
      };
      expect(controller.isFaceFrontalish(face, imageElement)).toBe(false);
    });
  });

  // ---- computeBlurVariance -----------------------------------------------
  describe('computeBlurVariance', () => {
    test('returns 0 for image narrower than 3px', () => {
      const img = { width: 2, height: 100 };
      expect(controller.computeBlurVariance(img)).toBe(0);
    });

    test('returns 0 for image shorter than 3px', () => {
      const img = { width: 100, height: 2 };
      expect(controller.computeBlurVariance(img)).toBe(0);
    });

    test('returns 0 for zero-dimension image', () => {
      const img = { width: 0, height: 0 };
      expect(controller.computeBlurVariance(img)).toBe(0);
    });

    test('returns 0 when canvas getContext returns null (jsdom)', () => {
      const img = { naturalWidth: 100, naturalHeight: 100 };
      expect(controller.computeBlurVariance(img)).toBe(0);
    });

    test('falls back to naturalWidth/naturalHeight', () => {
      const img = { naturalWidth: 200, naturalHeight: 200 };
      expect(controller.computeBlurVariance(img)).toBe(0);
    });
  });

  // ---- createAnalysisImage -----------------------------------------------
  describe('createAnalysisImage', () => {
    test('returns original element when image is smaller than maxAnalysisDimension', () => {
      const img = { naturalWidth: 300, naturalHeight: 200 };
      const result = controller.createAnalysisImage(img);
      expect(result.element).toBe(img);
      expect(result.scale).toBe(1);
      expect(result.width).toBe(300);
      expect(result.height).toBe(200);
    });

    test('returns original element when image equals maxAnalysisDimension', () => {
      const img = { naturalWidth: 640, naturalHeight: 480 };
      const result = controller.createAnalysisImage(img);
      expect(result.element).toBe(img);
      expect(result.scale).toBe(1);
    });

    test('returns downscaled canvas element when image exceeds maxAnalysisDimension', () => {
      const img = { naturalWidth: 1280, naturalHeight: 960 };
      const result = controller.createAnalysisImage(img);
      expect(result.scale).toBe(0.5);
      expect(result.element).not.toBe(img);
      expect(result.width).toBe(640);
      expect(result.height).toBe(480);
    });

    test('handles very large image', () => {
      const img = { naturalWidth: 6400, naturalHeight: 4800 };
      const result = controller.createAnalysisImage(img);
      expect(result.scale).toBeCloseTo(0.1);
      expect(result.width).toBe(640);
      expect(result.height).toBe(480);
    });

    test('handles portrait orientation', () => {
      const img = { naturalWidth: 960, naturalHeight: 1280 };
      const result = controller.createAnalysisImage(img);
      expect(result.scale).toBe(0.5);
      expect(result.width).toBe(480);
      expect(result.height).toBe(640);
    });
  });

  // ---- loadImage ---------------------------------------------------------
  describe('loadImage', () => {
    let OriginalFileReader;
    let OriginalImage;

    beforeEach(() => {
      OriginalFileReader = global.FileReader;
      OriginalImage = global.Image;
    });

    afterEach(() => {
      global.FileReader = OriginalFileReader;
      global.Image = OriginalImage;
    });

    test('resolves with image element on success', async () => {
      global.FileReader = class {
        readAsDataURL() {
          setTimeout(() => {
            this.onload({ target: { result: 'data:image/png;base64,abc' } });
          }, 0);
        }
      };

      let capturedImage;
      global.Image = class {
        constructor() {
          capturedImage = this;
        }
        set src(val) {
          this._src = val;
          setTimeout(() => this.onload(), 0);
        }
        get src() { return this._src; }
        get naturalWidth() { return 400; }
        get naturalHeight() { return 400; }
      };

      const file = new File(['data'], 'test.png', { type: 'image/png' });
      const result = await controller.loadImage(file);
      expect(result).toBe(capturedImage);
      expect(result.naturalWidth).toBe(400);
    });

    test('rejects when FileReader fails', async () => {
      global.FileReader = class {
        readAsDataURL() {
          setTimeout(() => {
            this.onerror(new Error('read error'));
          }, 0);
        }
      };

      const file = new File(['bad'], 'bad.png', { type: 'image/png' });
      await expect(controller.loadImage(file)).rejects.toThrow('could not be read');
    });

    test('rejects when Image fails to load', async () => {
      global.FileReader = class {
        readAsDataURL() {
          setTimeout(() => {
            this.onload({ target: { result: 'data:image/png;base64,abc' } });
          }, 0);
        }
      };

      global.Image = class {
        set src(val) {
          setTimeout(() => this.onerror(), 0);
        }
      };

      const file = new File(['data'], 'broken.png', { type: 'image/png' });
      await expect(controller.loadImage(file)).rejects.toThrow('not a valid image');
    });
  });

  // ---- DOM helpers -------------------------------------------------------
  describe('showInfo', () => {
    test('sets textContent and alert-info class', () => {
      controller.showInfo('Processing...');
      expect(controller.warningTarget.textContent).toBe('Processing...');
      expect(controller.warningTarget.classList.contains('alert-info')).toBe(true);
      expect(controller.warningTarget.classList.contains('d-none')).toBe(false);
    });

    test('does nothing when hasWarningTarget is false', () => {
      controller.hasWarningTarget = false;
      controller.warningTarget.textContent = 'unchanged';
      controller.showInfo('Should not appear');
      expect(controller.warningTarget.textContent).toBe('unchanged');
    });
  });

  describe('showError', () => {
    test('sets textContent and alert-danger class', () => {
      controller.showError('Something went wrong');
      expect(controller.warningTarget.textContent).toBe('Something went wrong');
      expect(controller.warningTarget.classList.contains('alert-danger')).toBe(true);
      expect(controller.warningTarget.classList.contains('d-none')).toBe(false);
    });

    test('falls back to alert() when hasWarningTarget is false', () => {
      controller.hasWarningTarget = false;
      const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});
      controller.showError('Alert message');
      expect(alertSpy).toHaveBeenCalledWith('Alert message');
      alertSpy.mockRestore();
    });
  });

  describe('clearWarning', () => {
    test('clears text and adds d-none class', () => {
      controller.warningTarget.textContent = 'old text';
      controller.warningTarget.className = 'alert alert-danger';
      controller.clearWarning();
      expect(controller.warningTarget.textContent).toBe('');
      expect(controller.warningTarget.className).toBe('d-none');
    });

    test('does nothing when hasWarningTarget is false', () => {
      controller.hasWarningTarget = false;
      controller.warningTarget.textContent = 'should stay';
      controller.clearWarning();
      expect(controller.warningTarget.textContent).toBe('should stay');
    });
  });

  describe('enableSubmit', () => {
    test('enables all submit buttons', () => {
      controller.submitButtonTargets.forEach(btn => { btn.disabled = true; });
      controller.enableSubmit();
      controller.submitButtonTargets.forEach(btn => {
        expect(btn.disabled).toBe(false);
      });
    });

    test('does nothing when hasSubmitButtonTarget is false', () => {
      controller.hasSubmitButtonTarget = false;
      controller.submitButtonTargets.forEach(btn => { btn.disabled = true; });
      controller.enableSubmit();
      controller.submitButtonTargets.forEach(btn => {
        expect(btn.disabled).toBe(true);
      });
    });
  });

  describe('disableSubmit', () => {
    test('disables all submit buttons', () => {
      controller.submitButtonTargets.forEach(btn => { btn.disabled = false; });
      controller.disableSubmit();
      controller.submitButtonTargets.forEach(btn => {
        expect(btn.disabled).toBe(true);
      });
    });

    test('does nothing when hasSubmitButtonTarget is false', () => {
      controller.hasSubmitButtonTarget = false;
      controller.submitButtonTargets.forEach(btn => { btn.disabled = false; });
      controller.disableSubmit();
      controller.submitButtonTargets.forEach(btn => {
        expect(btn.disabled).toBe(false);
      });
    });
  });

  // ---- dispatch ----------------------------------------------------------
  describe('dispatch', () => {
    test('dispatches CustomEvent with prefixed name on element', () => {
      const handler = jest.fn();
      controller.element.addEventListener('face-photo-validator:valid', handler);
      controller.dispatch('valid', { detail: { ok: true } });
      expect(handler).toHaveBeenCalledTimes(1);
      const event = handler.mock.calls[0][0];
      expect(event).toBeInstanceOf(CustomEvent);
      expect(event.detail).toEqual({ ok: true });
    });

    test('event bubbles and is cancelable', () => {
      const handler = jest.fn();
      const parent = document.createElement('div');
      parent.appendChild(controller.element);
      parent.addEventListener('face-photo-validator:test', handler);
      controller.dispatch('test');
      expect(handler).toHaveBeenCalledTimes(1);
      const event = handler.mock.calls[0][0];
      expect(event.bubbles).toBe(true);
      expect(event.cancelable).toBe(true);
    });

    test('works with no options', () => {
      const handler = jest.fn();
      controller.element.addEventListener('face-photo-validator:ping', handler);
      controller.dispatch('ping');
      expect(handler).toHaveBeenCalledTimes(1);
    });
  });

  // ---- validateFile additional rejection paths ---------------------------
  describe('validateFile rejection paths', () => {
    beforeEach(() => {
      controller.loadImage = jest.fn().mockResolvedValue({ naturalWidth: 400, naturalHeight: 400 });
      controller.ensureModelsReady = jest.fn().mockResolvedValue();
    });

    test('rejects when multiple faces are detected', async () => {
      controller.analyzeFace = jest.fn().mockResolvedValue({
        faceCount: 2,
        primaryFaceRatio: 0.2,
        primaryFaceFrontalish: true,
        blurVariance: 220,
      });
      const file = new File(['img'], 'multi.jpg', { type: 'image/jpeg' });
      await controller.validateFile({ target: { files: [file] } });

      expect(controller.warningTarget.textContent).toMatch(/[Mm]ultiple faces/);
      expect(controller.submitButtonTargets[0].disabled).toBe(true);
    });

    test('rejects when face is too small', async () => {
      controller.analyzeFace = jest.fn().mockResolvedValue({
        faceCount: 1,
        primaryFaceRatio: 0.01,
        primaryFaceFrontalish: true,
        blurVariance: 220,
      });
      const file = new File(['img'], 'faraway.jpg', { type: 'image/jpeg' });
      await controller.validateFile({ target: { files: [file] } });

      expect(controller.warningTarget.textContent).toMatch(/too small/i);
      expect(controller.submitButtonTargets[0].disabled).toBe(true);
    });

    test('rejects non-frontal face', async () => {
      controller.analyzeFace = jest.fn().mockResolvedValue({
        faceCount: 1,
        primaryFaceRatio: 0.2,
        primaryFaceFrontalish: false,
        blurVariance: 220,
      });
      const file = new File(['img'], 'profile.jpg', { type: 'image/jpeg' });
      await controller.validateFile({ target: { files: [file] } });

      expect(controller.warningTarget.textContent).toMatch(/front-facing/i);
      expect(controller.submitButtonTargets[0].disabled).toBe(true);
    });

    test('rejects blurry image', async () => {
      controller.analyzeFace = jest.fn().mockResolvedValue({
        faceCount: 1,
        primaryFaceRatio: 0.2,
        primaryFaceFrontalish: true,
        blurVariance: 10,
      });
      const file = new File(['img'], 'blurry.jpg', { type: 'image/jpeg' });
      await controller.validateFile({ target: { files: [file] } });

      expect(controller.warningTarget.textContent).toMatch(/blurry/i);
      expect(controller.submitButtonTargets[0].disabled).toBe(true);
    });

    test('rejects image too small (validateImageDimensions throws)', async () => {
      controller.loadImage = jest.fn().mockResolvedValue({ naturalWidth: 100, naturalHeight: 100 });
      const file = new File(['img'], 'tiny.jpg', { type: 'image/jpeg' });
      await controller.validateFile({ target: { files: [file] } });

      expect(controller.warningTarget.textContent).toMatch(/too small/i);
      expect(controller.submitButtonTargets[0].disabled).toBe(true);
    });

    test('dispatches invalid event on rejection', async () => {
      controller.analyzeFace = jest.fn().mockResolvedValue({
        faceCount: 0,
        primaryFaceRatio: 0,
        primaryFaceFrontalish: false,
        blurVariance: 220,
      });
      const handler = jest.fn();
      controller.element.addEventListener('face-photo-validator:invalid', handler);

      const file = new File(['img'], 'noface.jpg', { type: 'image/jpeg' });
      await controller.validateFile({ target: { files: [file] } });

      expect(handler).toHaveBeenCalledTimes(1);
      expect(handler.mock.calls[0][0].detail.message).toMatch(/[Nn]o face/);
    });

    test('dispatches valid event on success', async () => {
      controller.analyzeFace = jest.fn().mockResolvedValue({
        faceCount: 1,
        faces: [],
        primaryFaceRatio: 0.2,
        primaryFaceFrontalish: true,
        blurVariance: 220,
      });
      const handler = jest.fn();
      controller.element.addEventListener('face-photo-validator:valid', handler);

      const file = new File(['img'], 'good.jpg', { type: 'image/jpeg' });
      await controller.validateFile({ target: { files: [file] } });

      expect(handler).toHaveBeenCalledTimes(1);
      expect(handler.mock.calls[0][0].detail.faceCount).toBe(1);
    });

    test('handles non-Error thrown from analyzeFace', async () => {
      controller.analyzeFace = jest.fn().mockRejectedValue('string-error');
      const file = new File(['img'], 'fail.jpg', { type: 'image/jpeg' });
      await controller.validateFile({ target: { files: [file] } });

      expect(controller.warningTarget.textContent).toMatch(/Unable to validate/);
      expect(controller.submitButtonTargets[0].disabled).toBe(true);
    });
  });

  // ---- ensureModelsReady -------------------------------------------------
  describe('ensureModelsReady', () => {
    beforeEach(() => {
      jest.resetModules();
    });

    test('loads models from default URL when modelBaseUrlValue is not set', async () => {
      jest.mock('face-api.js', () => ({
        nets: {
          tinyFaceDetector: { loadFromUri: jest.fn().mockResolvedValue() },
          faceLandmark68TinyNet: { loadFromUri: jest.fn().mockResolvedValue() },
        },
        TinyFaceDetectorOptions: function TinyFaceDetectorOptions() {},
        detectAllFaces: jest.fn(),
      }));
      const { default: FreshController } = require('../../../assets/js/controllers/face-photo-validator-controller.js');
      const freshFaceapi = require('face-api.js');

      const ctrl = new FreshController();
      ctrl.element = document.createElement('div');
      ctrl.debugValue = false;
      ctrl.hasModelBaseUrlValue = false;
      ctrl.modelBaseUrlValue = '';

      await ctrl.ensureModelsReady();

      expect(freshFaceapi.nets.tinyFaceDetector.loadFromUri).toHaveBeenCalledWith('/models/face-api');
      expect(freshFaceapi.nets.faceLandmark68TinyNet.loadFromUri).toHaveBeenCalledWith('/models/face-api');
    });

    test('loads models from custom URL when modelBaseUrlValue is set', async () => {
      jest.mock('face-api.js', () => ({
        nets: {
          tinyFaceDetector: { loadFromUri: jest.fn().mockResolvedValue() },
          faceLandmark68TinyNet: { loadFromUri: jest.fn().mockResolvedValue() },
        },
        TinyFaceDetectorOptions: function TinyFaceDetectorOptions() {},
        detectAllFaces: jest.fn(),
      }));
      const { default: FreshController } = require('../../../assets/js/controllers/face-photo-validator-controller.js');
      const freshFaceapi = require('face-api.js');

      const ctrl = new FreshController();
      ctrl.element = document.createElement('div');
      ctrl.debugValue = false;
      ctrl.hasModelBaseUrlValue = true;
      ctrl.modelBaseUrlValue = '/custom/models';

      await ctrl.ensureModelsReady();

      expect(freshFaceapi.nets.tinyFaceDetector.loadFromUri).toHaveBeenCalledWith('/custom/models');
    });

    test('caches promise on second call', async () => {
      jest.mock('face-api.js', () => ({
        nets: {
          tinyFaceDetector: { loadFromUri: jest.fn().mockResolvedValue() },
          faceLandmark68TinyNet: { loadFromUri: jest.fn().mockResolvedValue() },
        },
        TinyFaceDetectorOptions: function TinyFaceDetectorOptions() {},
        detectAllFaces: jest.fn(),
      }));
      const { default: FreshController } = require('../../../assets/js/controllers/face-photo-validator-controller.js');
      const freshFaceapi = require('face-api.js');

      const ctrl = new FreshController();
      ctrl.element = document.createElement('div');
      ctrl.debugValue = false;
      ctrl.hasModelBaseUrlValue = false;
      ctrl.modelBaseUrlValue = '';

      await ctrl.ensureModelsReady();
      await ctrl.ensureModelsReady();

      expect(freshFaceapi.nets.tinyFaceDetector.loadFromUri).toHaveBeenCalledTimes(1);
      expect(freshFaceapi.nets.faceLandmark68TinyNet.loadFromUri).toHaveBeenCalledTimes(1);
    });

    test('resets cache on model load failure and re-throws', async () => {
      jest.mock('face-api.js', () => ({
        nets: {
          tinyFaceDetector: { loadFromUri: jest.fn().mockRejectedValue(new Error('network error')) },
          faceLandmark68TinyNet: { loadFromUri: jest.fn().mockResolvedValue() },
        },
        TinyFaceDetectorOptions: function TinyFaceDetectorOptions() {},
        detectAllFaces: jest.fn(),
      }));
      const { default: FreshController } = require('../../../assets/js/controllers/face-photo-validator-controller.js');

      const ctrl = new FreshController();
      ctrl.element = document.createElement('div');
      ctrl.debugValue = false;
      ctrl.hasModelBaseUrlValue = false;
      ctrl.modelBaseUrlValue = '';

      await expect(ctrl.ensureModelsReady()).rejects.toThrow('network error');
      const freshFaceapi = require('face-api.js');
      await expect(ctrl.ensureModelsReady()).rejects.toThrow('network error');
      expect(freshFaceapi.nets.tinyFaceDetector.loadFromUri).toHaveBeenCalledTimes(2);
    });
  });

  // ---- logDebug ----------------------------------------------------------
  describe('logDebug', () => {
    test('logs message when debug is true', () => {
      const spy = jest.spyOn(console, 'log').mockImplementation(() => {});
      controller.debugValue = true;
      controller.logDebug('test message');
      expect(spy).toHaveBeenCalledWith('[face-photo-validator]', 'test message');
      spy.mockRestore();
    });

    test('logs message with data when debug is true', () => {
      const spy = jest.spyOn(console, 'log').mockImplementation(() => {});
      controller.debugValue = true;
      controller.logDebug('test', { key: 'val' });
      expect(spy).toHaveBeenCalledWith('[face-photo-validator]', 'test', { key: 'val' });
      spy.mockRestore();
    });

    test('does not log when debug is false', () => {
      const spy = jest.spyOn(console, 'log').mockImplementation(() => {});
      controller.debugValue = false;
      controller.logDebug('hidden message');
      expect(spy).not.toHaveBeenCalled();
      spy.mockRestore();
    });
  });

  // ---- connect -----------------------------------------------------------
  describe('connect', () => {
    test('disables submit on connect', () => {
      controller.submitButtonTargets.forEach(btn => { btn.disabled = false; });
      controller.connect();
      controller.submitButtonTargets.forEach(btn => {
        expect(btn.disabled).toBe(true);
      });
    });
  });
});
