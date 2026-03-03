class TheftReport {
  const TheftReport({
    required this.reporterName,
    required this.phone,
    required this.meterNumber,
    required this.consumerName,
    required this.location,
    required this.incidentDate,
    required this.theftType,
    required this.details,
    required this.latitude,
    required this.longitude,
  });

  final String reporterName;
  final String phone;
  final String meterNumber;
  final String consumerName;
  final String location;
  final DateTime incidentDate;
  final String theftType;
  final String details;
  final double? latitude;
  final double? longitude;

  Map<String, dynamic> toJson() {
    return {
      'reporterName': reporterName,
      'phone': phone,
      'meterNumber': meterNumber,
      'consumerName': consumerName,
      'location': location,
      'incidentDate': incidentDate.toIso8601String(),
      'theftType': theftType,
      'details': details,
      'latitude': latitude,
      'longitude': longitude,
    };
  }
}

class VideoChunk {
  const VideoChunk({
    required this.chunkIndex,
    required this.totalChunks,
    required this.bytes,
    required this.fileId,
  });

  final int chunkIndex;
  final int totalChunks;
  final List<int> bytes;
  final String fileId;
}
