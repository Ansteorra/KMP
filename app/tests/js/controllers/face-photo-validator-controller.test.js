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
