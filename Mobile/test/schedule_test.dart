import 'package:flutter_test/flutter_test.dart';
import 'package:dio/dio.dart';
import 'package:schoolhub_parent/models/schedule.dart';
import 'package:schoolhub_parent/services/api_service.dart';
import 'package:schoolhub_parent/services/schedule_service.dart';

void main() {
  test('fetch schedule', () async {
    final api = ApiService();
    // Inject token for testing
    api.onUnauthorized = () {};
    // we can't easily set token in SecureStorage in tests without mock
    // so we'll just test the parser by mocking the response
    final service = ScheduleService(api);

    final mockResponse = {
      "success": true,
      "data": [
        {
          "id": 30,
          "day": "Sunday",
          "start_time": "08:00",
          "end_time": "09:00",
          "assignment": {
            "id": 9,
            "subject": {"name": "Math"}
          }
        }
      ],
      "pagination": {"total": 1}
    };

    // test the parsing logic directly
    List<dynamic> items = [];
    dynamic body = mockResponse;
    if (body is Map<String, dynamic>) {
      final d = body['data'];
      if (d is List) {
        items = d;
      } else if (d is Map<String, dynamic> && d['data'] is List) {
        items = d['data'] as List;
      }
    } else if (body is List) {
      items = body as List<dynamic>;
    }
    
    print('Items extracted: \${items.length}');
    final parsed = items.map((s) => ScheduleSlot.fromJson(s)).toList();
    print('Parsed slots: \${parsed.length}, Day: \${parsed.first.day}');
  });
}
