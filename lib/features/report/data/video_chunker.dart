import 'dart:io';

import 'package:flutter_vigi/features/report/domain/theft_report.dart';

class VideoChunker {
  const VideoChunker();

  Future<List<VideoChunk>> splitFile({
    required File file,
    required int chunkSizeInBytes,
    required String fileId,
  }) async {
    final bytes = await file.readAsBytes();
    final totalChunks = (bytes.length / chunkSizeInBytes).ceil();

    final chunks = <VideoChunk>[];

    for (var index = 0; index < totalChunks; index++) {
      final start = index * chunkSizeInBytes;
      final end = (start + chunkSizeInBytes) > bytes.length
          ? bytes.length
          : start + chunkSizeInBytes;

      chunks.add(
        VideoChunk(
          chunkIndex: index,
          totalChunks: totalChunks,
          bytes: bytes.sublist(start, end),
          fileId: fileId,
        ),
      );
    }

    return chunks;
  }
}
