import 'dart:io';

import 'package:camera/camera.dart';

class VideoRecordingService {
  CameraController? _controller;

  CameraController? get controller => _controller;

  Future<void> initialize() async {
    final cameras = await availableCameras();
    final backCamera = cameras.firstWhere(
      (camera) => camera.lensDirection == CameraLensDirection.back,
      orElse: () => cameras.first,
    );

    _controller = CameraController(
      backCamera,
      ResolutionPreset.medium,
      enableAudio: true,
    );

    await _controller!.initialize();
  }

  Future<void> start() async {
    final controller = _controller;
    if (controller == null || !controller.value.isInitialized) {
      throw Exception('Camera is not initialized');
    }

    if (controller.value.isRecordingVideo) {
      return;
    }

    await controller.startVideoRecording();
  }

  Future<File> stop() async {
    final controller = _controller;
    if (controller == null || !controller.value.isInitialized) {
      throw Exception('Camera is not initialized');
    }

    if (!controller.value.isRecordingVideo) {
      throw Exception('Video is not recording');
    }

    final file = await controller.stopVideoRecording();
    return File(file.path);
  }

  Future<void> dispose() async {
    await _controller?.dispose();
    _controller = null;
  }
}
