jest.mock('face-api.js', () => ({
  __esModule: true,
  nets: {
    tinyFaceDetector: { loadFromUri: jest.fn().mockResolvedValue() },
    faceLandmark68TinyNet: { loadFromUri: jest.fn().mockResolvedValue() },
  },
  TinyFaceDetectorOptions: jest.fn(function TinyFaceDetectorOptions(options) {
    Object.assign(this, options);
  }),
  detectAllFaces: jest.fn(),
}));

import * as faceapi from 'face-api.js';
import FacePhotoValidatorController from '../../../assets/js/controllers/face-photo-validator-controller.js';

function createController() {
  const controller = new FacePhotoValidatorController();
  controller.element = document.createElement('div');
  controller.maxAnalysisDimensionValue = 640;
  controller.edgeMarginRatioValue = 0.02;
  controller.minFaceAspectRatioValue = 0.72;
  controller.maxFaceAspectRatioValue = 1.35;
  controller.debugValue = false;

  return controller;
}

function mockCanvasContext(grayRows) {
  const originalCreateElement = document.createElement.bind(document);
  const flatGrayValues = grayRows.flat();
  const rgbaValues = [];

  flatGrayValues.forEach((value) => {
    rgbaValues.push(value, value, value, 255);
  });

  const context = {
    drawImage: jest.fn(),
    getImageData: jest.fn().mockReturnValue({
      data: Uint8ClampedArray.from(rgbaValues),
    }),
    strokeRect: jest.fn(),
    fillText: jest.fn(),
  };
  const canvas = {
    width: 0,
    height: 0,
    getContext: jest.fn().mockReturnValue(context),
  };

  jest.spyOn(document, 'createElement').mockImplementation((tagName) => {
    if (tagName === 'canvas') {
      return canvas;
    }

    return originalCreateElement(tagName);
  });

  return { canvas, context };
}

describe('FacePhotoValidatorController analysis pipeline', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    jest.clearAllMocks();
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  test('analyzeFace scales detections, selects the largest face, and reports blur metrics', async () => {
    const analysisCanvas = { nodeName: 'CANVAS' };
    const imageElement = { naturalWidth: 400, naturalHeight: 400 };
    const firstLandmarks = { id: 'first' };
    const secondLandmarks = { id: 'second' };
    const withFaceLandmarks = jest.fn().mockResolvedValue([
      {
        detection: {
          box: { x: 50, y: 100, width: 80, height: 120 },
          score: 0.93,
        },
        landmarks: firstLandmarks,
      },
      {
        detection: {
          box: { x: 20, y: 40, width: 40, height: 60 },
          score: 0.67,
        },
        landmarks: secondLandmarks,
      },
    ]);

    controller.createAnalysisImage = jest.fn().mockReturnValue({
      element: analysisCanvas,
      scale: 0.5,
      width: 200,
      height: 200,
    });
    controller.computeFrontalMetrics = jest
      .fn()
      .mockReturnValueOnce({
        yawAsymmetry: 0.04,
        roll: 0.02,
        noseToMouthHorizontalOffset: 0.05,
        frontalish: true,
      })
      .mockReturnValueOnce({
        yawAsymmetry: 0.08,
        roll: 0.03,
        noseToMouthHorizontalOffset: 0.09,
        frontalish: true,
      });
    controller.computeBlurVariance = jest.fn().mockReturnValue(88);
    controller.isFaceFrontalish = jest.fn().mockReturnValue(true);
    faceapi.detectAllFaces.mockReturnValue({ withFaceLandmarks });

    const analysis = await controller.analyzeFace(imageElement);

    expect(faceapi.TinyFaceDetectorOptions).toHaveBeenCalledWith({
      inputSize: 416,
      scoreThreshold: 0.45,
    });
    expect(faceapi.detectAllFaces).toHaveBeenCalledWith(
      analysisCanvas,
      expect.objectContaining({
        inputSize: 416,
        scoreThreshold: 0.45,
      })
    );
    expect(withFaceLandmarks).toHaveBeenCalledWith(true);
    expect(controller.computeFrontalMetrics).toHaveBeenNthCalledWith(1, firstLandmarks, 0.5);
    expect(controller.computeFrontalMetrics).toHaveBeenNthCalledWith(2, secondLandmarks, 0.5);
    expect(controller.computeBlurVariance).toHaveBeenCalledWith(analysisCanvas);
    expect(analysis.faces).toEqual([
      expect.objectContaining({
        x: 100,
        y: 200,
        width: 160,
        height: 240,
        area: 38400,
        score: 0.93,
      }),
      expect.objectContaining({
        x: 40,
        y: 80,
        width: 80,
        height: 120,
        area: 9600,
        score: 0.67,
      }),
    ]);
    expect(analysis.primaryFace).toEqual(
      expect.objectContaining({
        x: 100,
        y: 200,
        width: 160,
        height: 240,
        area: 38400,
      })
    );
    expect(controller.isFaceFrontalish).toHaveBeenCalledWith(
      expect.objectContaining({ x: 100, y: 200, width: 160, height: 240 }),
      imageElement
    );
    expect(analysis.faceCount).toBe(2);
    expect(analysis.primaryFaceRatio).toBeCloseTo(38400 / 160000, 5);
    expect(analysis.primaryFaceFrontalish).toBe(true);
    expect(analysis.blurVariance).toBe(88);
    expect(analysis.analysisScale).toBe(0.5);
    expect(analysis.analysisWidth).toBe(200);
    expect(analysis.analysisHeight).toBe(200);
  });

  test('analyzeFace returns zeroed face metrics when no face is detected', async () => {
    const imageElement = { naturalWidth: 400, naturalHeight: 400 };
    const withFaceLandmarks = jest.fn().mockResolvedValue([]);

    controller.createAnalysisImage = jest.fn().mockReturnValue({
      element: imageElement,
      scale: 1,
      width: 400,
      height: 400,
    });
    controller.computeBlurVariance = jest.fn().mockReturnValue(21);
    controller.isFaceFrontalish = jest.fn();
    faceapi.detectAllFaces.mockReturnValue({ withFaceLandmarks });

    const analysis = await controller.analyzeFace(imageElement);

    expect(withFaceLandmarks).toHaveBeenCalledWith(true);
    expect(analysis).toEqual(
      expect.objectContaining({
        faceCount: 0,
        faces: [],
        primaryFace: null,
        primaryFaceRatio: 0,
        primaryFaceFrontalish: false,
        blurVariance: 21,
        analysisScale: 1,
        analysisWidth: 400,
        analysisHeight: 400,
      })
    );
    expect(controller.isFaceFrontalish).not.toHaveBeenCalled();
  });

  test('computeBlurVariance detects contrast in a sharpened image', () => {
    const imageSource = { width: 5, height: 5 };
    const { context } = mockCanvasContext([
      [0, 0, 0, 0, 0],
      [0, 0, 255, 0, 0],
      [0, 255, 255, 255, 0],
      [0, 0, 255, 0, 0],
      [0, 0, 0, 0, 0],
    ]);

    const variance = controller.computeBlurVariance(imageSource);

    expect(context.drawImage).toHaveBeenCalledWith(imageSource, 0, 0, 5, 5);
    expect(variance).toBeGreaterThan(0);
  });

  test('computeBlurVariance returns zero for a flat image with no edges', () => {
    mockCanvasContext([
      [120, 120, 120],
      [120, 120, 120],
      [120, 120, 120],
    ]);

    expect(controller.computeBlurVariance({ width: 3, height: 3 })).toBe(0);
  });

  test('logDetectedFacesPreview draws and logs the debug overlay', () => {
    const imageElement = { naturalWidth: 300, naturalHeight: 200 };
    const { canvas, context } = mockCanvasContext([
      [0, 0, 0],
      [0, 0, 0],
      [0, 0, 0],
    ]);
    const consoleSpy = jest.spyOn(console, 'log').mockImplementation(() => {});

    controller.debugValue = true;
    controller.logDetectedFacesPreview(imageElement, [
      { x: 10, y: 20, width: 30, height: 40 },
      { x: 80, y: 90, width: 50, height: 60 },
    ]);

    expect(context.drawImage).toHaveBeenCalledWith(imageElement, 0, 0);
    expect(context.strokeRect).toHaveBeenNthCalledWith(1, 10, 20, 30, 40);
    expect(context.strokeRect).toHaveBeenNthCalledWith(2, 80, 90, 50, 60);
    expect(context.fillText).toHaveBeenNthCalledWith(1, 'Face 1', 14, 18);
    expect(context.fillText).toHaveBeenNthCalledWith(2, 'Face 2', 84, 84);
    expect(consoleSpy).toHaveBeenCalledWith(
      '[face-photo-validator] Detection overlay (red boxes)',
      canvas
    );
  });
});
