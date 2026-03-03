import 'package:flutter/material.dart';
import 'package:flutter_vigi/core/app_theme.dart';
import 'package:flutter_vigi/features/report/presentation/theft_report_page.dart';

class VigilanceApp extends StatelessWidget {
  const VigilanceApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Vigilance Meter Theft',
      theme: AppTheme.lightTheme,
      home: const TheftReportPage(),
    );
  }
}
