import 'dart:io';

import 'package:camera/camera.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_vigi/core/app_config.dart';
import 'package:flutter_vigi/features/report/data/upload_service.dart';
import 'package:flutter_vigi/features/report/data/video_chunker.dart';
import 'package:flutter_vigi/features/report/data/video_recording_service.dart';
import 'package:flutter_vigi/features/report/domain/theft_report.dart';
import 'package:intl/intl.dart';
import 'package:uuid/uuid.dart';

class TheftReportPage extends StatefulWidget {
  const TheftReportPage({super.key});

  @override
  State<TheftReportPage> createState() => _TheftReportPageState();
}

class _TheftReportPageState extends State<TheftReportPage> {
  final _formKey = GlobalKey<FormState>();
  final _reporterNameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _meterNumberController = TextEditingController();
  final _consumerNameController = TextEditingController();
  final _locationController = TextEditingController();
  final _detailsController = TextEditingController();

  final _recordingService = VideoRecordingService();
  final _chunker = VideoChunker();
  final _uploadService = const UploadService();
  final _uuid = const Uuid();

  DateTime _incidentDate = DateTime.now();
  String _theftType = 'Meter Bypass';
  bool _isCameraReady = false;
  bool _isRecording = false;
  bool _isUploading = false;
  double _uploadProgress = 0;
  File? _recordedVideo;
  File? _selectedEvidenceFile;
  String? _pendingFileId;
  String? _pendingReportId;
  bool _isReportUploaded = false;
  List<VideoChunk>? _pendingChunks;

  final List<String> _theftTypes = const [
    'Meter Bypass',
    'Direct Hooking',
    'Meter Tampering',
    'Seal Broken',
  ];

  @override
  void initState() {
    super.initState();
    _initializeCamera();
  }

  Future<void> _initializeCamera() async {
    try {
      await _recordingService.initialize();
      if (!mounted) return;
      setState(() {
        _isCameraReady = true;
      });
    } catch (_) {
      if (!mounted) return;
      _showSnack('Camera not available on this device.');
    }
  }

  @override
  void dispose() {
    _reporterNameController.dispose();
    _phoneController.dispose();
    _meterNumberController.dispose();
    _consumerNameController.dispose();
    _locationController.dispose();
    _detailsController.dispose();
    _recordingService.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final selected = await showDatePicker(
      context: context,
      initialDate: _incidentDate,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
    );

    if (selected != null) {
      setState(() {
        _incidentDate = selected;
      });
    }
  }

  Future<void> _toggleRecording() async {
    if (!_isCameraReady || _isUploading) return;

    try {
      if (_isRecording) {
        final file = await _recordingService.stop();
        setState(() {
          _isRecording = false;
          _recordedVideo = file;
          _selectedEvidenceFile = file;
          _pendingFileId = null;
          _pendingReportId = null;
          _isReportUploaded = false;
          _pendingChunks = null;
        });
        _showSnack('Video recorded successfully.');
      } else {
        await _recordingService.start();
        setState(() {
          _isRecording = true;
        });
      }
    } catch (e) {
      _showSnack('Recording error: $e');
    }
  }

  Future<void> _pickEvidenceFile() async {
    if (_isUploading || _isRecording) return;

    try {
      final result = await FilePicker.platform.pickFiles(
        allowMultiple: false,
        type: FileType.any,
      );

      if (result == null || result.files.isEmpty) {
        return;
      }

      final picked = result.files.first;
      if (picked.path == null || picked.path!.trim().isEmpty) {
        _showSnack('Unable to access selected file path.');
        return;
      }

      setState(() {
        _selectedEvidenceFile = File(picked.path!);
        _recordedVideo = null;
        _pendingFileId = null;
        _pendingReportId = null;
        _isReportUploaded = false;
        _pendingChunks = null;
      });

      _showSnack('Evidence file selected successfully.');
    } catch (error) {
      _showSnack('File selection failed: $error');
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final evidenceFile = _selectedEvidenceFile ?? _recordedVideo;

    if (evidenceFile == null) {
      _showSnack('Please record or select evidence file before submit.');
      return;
    }

    final reportId = _pendingReportId ?? _uuid.v4();
    var fileId = _pendingFileId ?? _uuid.v4();

    _pendingReportId = reportId;
    _pendingFileId = fileId;

    final report = TheftReport(
      reporterName: _reporterNameController.text.trim(),
      phone: _phoneController.text.trim(),
      meterNumber: _meterNumberController.text.trim(),
      consumerName: _consumerNameController.text.trim(),
      location: _locationController.text.trim(),
      incidentDate: _incidentDate,
      theftType: _theftType,
      details: _detailsController.text.trim(),
      latitude: null,
      longitude: null,
    );

    setState(() {
      _isUploading = true;
      _uploadProgress = 0;
    });

    try {
      await _uploadService.verifyBackendReachable();

      if (!_isReportUploaded) {
        await _uploadService.uploadReport(report: report, reportId: reportId);
        _isReportUploaded = true;
      }

      final chunks =
          _pendingChunks ??
          await _chunker.splitFile(
            file: evidenceFile,
            chunkSizeInBytes: 1024 * 1024,
            fileId: fileId,
          );

      final init = await _uploadService.initUpload(
        totalChunks: chunks.length,
        fileId: fileId,
      );
      fileId = init.fileId;
      _pendingFileId = fileId;

      if (fileId != chunks.first.fileId) {
        _pendingChunks = await _chunker.splitFile(
          file: evidenceFile,
          chunkSizeInBytes: 1024 * 1024,
          fileId: fileId,
        );
      }

      final activeChunks = _pendingChunks ?? chunks;
      _pendingChunks = activeChunks;

      final status = await _uploadService.getUploadStatus(
        fileId: fileId,
        totalChunks: activeChunks.length,
      );
      final alreadyUploaded = {
        ...init.uploadedIndexes,
        ...status.uploadedIndexes,
      };

      if (mounted) {
        setState(() {
          _uploadProgress = alreadyUploaded.length / activeChunks.length;
        });
      }

      var uploadedCount = alreadyUploaded.length;

      for (var index = 0; index < activeChunks.length; index++) {
        if (alreadyUploaded.contains(index)) {
          continue;
        }

        await _uploadService.uploadChunk(activeChunks[index]);
        uploadedCount += 1;
        if (!mounted) return;
        setState(() {
          _uploadProgress = uploadedCount / activeChunks.length;
        });
      }

      await _uploadService.finalizeUpload(
        fileId: fileId,
        totalChunks: activeChunks.length,
        fileName: evidenceFile.uri.pathSegments.isNotEmpty
            ? evidenceFile.uri.pathSegments.last
            : null,
        mimeType: _detectMimeType(evidenceFile.path),
      );

      if (!mounted) return;
      _showSnack('Report submitted and evidence uploaded successfully.');
      _resetForm();
    } catch (e) {
      _showSnack('Submit failed: $e. Retry submit to resume from last chunk.');
    } finally {
      if (mounted) {
        setState(() {
          _isUploading = false;
        });
      }
    }
  }

  void _resetForm() {
    _formKey.currentState?.reset();
    _reporterNameController.clear();
    _phoneController.clear();
    _meterNumberController.clear();
    _consumerNameController.clear();
    _locationController.clear();
    _detailsController.clear();

    setState(() {
      _incidentDate = DateTime.now();
      _theftType = _theftTypes.first;
      _recordedVideo = null;
      _selectedEvidenceFile = null;
      _uploadProgress = 0;
      _pendingFileId = null;
      _pendingReportId = null;
      _isReportUploaded = false;
      _pendingChunks = null;
    });
  }

  void _showSnack(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  String _detectMimeType(String filePath) {
    final extension = filePath.split('.').last.toLowerCase();
    switch (extension) {
      case 'mp4':
        return 'video/mp4';
      case 'mov':
        return 'video/quicktime';
      case 'mkv':
        return 'video/x-matroska';
      case 'avi':
        return 'video/x-msvideo';
      case 'webm':
        return 'video/webm';
      case 'jpg':
      case 'jpeg':
        return 'image/jpeg';
      case 'png':
        return 'image/png';
      case 'pdf':
        return 'application/pdf';
      default:
        return 'application/octet-stream';
    }
  }

  @override
  Widget build(BuildContext context) {
    final dateLabel = DateFormat('dd MMM yyyy').format(_incidentDate);

    return Scaffold(
      appBar: AppBar(title: const Text('Vigilance Theft Reporting')),
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _flavorBanner(),
              const SizedBox(height: 12),
              _sectionCard(
                title: 'Inspection Details',
                child: Column(
                  children: [
                    _field(
                      controller: _reporterNameController,
                      label: 'Reporter Name',
                    ),
                    const SizedBox(height: 12),
                    _field(
                      controller: _phoneController,
                      label: 'Phone Number',
                      keyboardType: TextInputType.phone,
                    ),
                    const SizedBox(height: 12),
                    _field(
                      controller: _meterNumberController,
                      label: 'Meter Number',
                    ),
                    const SizedBox(height: 12),
                    _field(
                      controller: _consumerNameController,
                      label: 'Consumer Name',
                    ),
                    const SizedBox(height: 12),
                    _field(
                      controller: _locationController,
                      label: 'Location Address',
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      initialValue: _theftType,
                      decoration: const InputDecoration(
                        labelText: 'Theft Type',
                      ),
                      items: _theftTypes
                          .map(
                            (type) => DropdownMenuItem<String>(
                              value: type,
                              child: Text(type),
                            ),
                          )
                          .toList(),
                      onChanged: (value) {
                        if (value != null) {
                          setState(() {
                            _theftType = value;
                          });
                        }
                      },
                    ),
                    const SizedBox(height: 12),
                    InkWell(
                      onTap: _pickDate,
                      borderRadius: BorderRadius.circular(12),
                      child: InputDecorator(
                        decoration: const InputDecoration(
                          labelText: 'Incident Date',
                        ),
                        child: Text(dateLabel),
                      ),
                    ),
                    const SizedBox(height: 12),
                    _field(
                      controller: _detailsController,
                      label: 'Detailed Observation',
                      maxLines: 4,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              _sectionCard(
                title: 'Evidence (Video or Document)',
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    AspectRatio(
                      aspectRatio: 16 / 9,
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: _cameraPreview(),
                      ),
                    ),
                    const SizedBox(height: 10),
                    ElevatedButton.icon(
                      onPressed: _isUploading ? null : _toggleRecording,
                      icon: Icon(
                        _isRecording
                            ? Icons.stop_circle
                            : Icons.fiber_manual_record,
                      ),
                      label: Text(
                        _isRecording ? 'Stop Recording' : 'Start Recording',
                      ),
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed: _isUploading || _isRecording
                          ? null
                          : _pickEvidenceFile,
                      icon: const Icon(Icons.attach_file),
                      label: const Text('Select Evidence File'),
                    ),
                    if (_recordedVideo != null) ...[
                      const SizedBox(height: 8),
                      Text(
                        'Captured: ${_recordedVideo!.path.split('/').last}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                    if (_selectedEvidenceFile != null) ...[
                      const SizedBox(height: 8),
                      Text(
                        'Selected: ${_selectedEvidenceFile!.path.split('/').last}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 16),
              _sectionCard(
                title: 'Upload Status',
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    LinearProgressIndicator(
                      value: _isUploading ? _uploadProgress : 0,
                      minHeight: 8,
                      borderRadius: BorderRadius.circular(99),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _isUploading
                          ? 'Uploading ${(100 * _uploadProgress).toStringAsFixed(0)}%'
                          : 'Ready to submit report',
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                height: 50,
                child: FilledButton(
                  onPressed: _isUploading ? null : _submit,
                  child: Text(
                    _isUploading ? 'Submitting...' : 'Submit Theft Report',
                  ),
                ),
              ),
              const SizedBox(height: 18),
            ],
          ),
        ),
      ),
    );
  }

  Widget _cameraPreview() {
    final controller = _recordingService.controller;
    if (!_isCameraReady ||
        controller == null ||
        !controller.value.isInitialized) {
      return Container(
        color: const Color(0xFFE8EAF6),
        alignment: Alignment.center,
        child: const Text('Camera preview unavailable'),
      );
    }
    return CameraPreview(controller);
  }

  Widget _flavorBanner() {
    final colorScheme = Theme.of(context).colorScheme;
    final flavor = AppConfig.appFlavorLabel;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: colorScheme.primaryContainer,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(
            Icons.flag_circle,
            size: 18,
            color: colorScheme.onPrimaryContainer,
          ),
          const SizedBox(width: 8),
          Text(
            'Environment: $flavor',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w700,
              color: colorScheme.onPrimaryContainer,
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionCard({required String title, required Widget child}) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE7E9F5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }

  Widget _field({
    required TextEditingController controller,
    required String label,
    TextInputType? keyboardType,
    int maxLines = 1,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      maxLines: maxLines,
      decoration: InputDecoration(labelText: label),
      validator: (value) {
        if (value == null || value.trim().isEmpty) {
          return 'Required';
        }
        return null;
      },
    );
  }
}
