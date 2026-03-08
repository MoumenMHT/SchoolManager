import ApiService from './ApiService';

export interface AttendanceRecord {
  id: number;
  student_id: number;
  subject_id: number | null;
  teacher_id: number;
  schedule_id: number | null;
  date: string;
  time: string | null;
  status: 'present' | 'absent' | 'late' | 'excused';
  reason: string | null;
  created_at: string;
  updated_at: string;
  student?: {
    id: number;
    first_name: string;
    last_name: string;
    code: string;
  };
  subject?: {
    id: number;
    name: string;
  };
}

export interface CreateAttendanceDTO {
  student_id: number;
  subject_id?: number | null;
  teacher_id: number;
  schedule_id?: number | null;
  status: 'present' | 'absent' | 'late' | 'excused';
  reason?: string | null;
  date?: string | null;
}

export interface UpdateAttendanceDTO {
  status: 'present' | 'absent' | 'late' | 'excused';
  reason?: string | null;
}

class AttendanceService {
  async getClassAttendances(
    classId: number,
    params?: { date?: string; start_date?: string; end_date?: string; subject_id?: number; schedule_id?: number }
  ): Promise<AttendanceRecord[]> {
    const query: Record<string, any> = {};
    if (params?.date) {
      query.start_date = params.date;
      query.end_date = params.date;
    } else {
      if (params?.start_date) query.start_date = params.start_date;
      if (params?.end_date) query.end_date = params.end_date;
    }
    if (params?.subject_id) query.subject_id = params.subject_id;
    if (params?.schedule_id) query.schedule_id = params.schedule_id;

    const response = await ApiService.get<AttendanceRecord[]>(`/classes/${classId}/attendances`, query);
    return (response.data as any)?.data ?? response.data ?? [];
  }

  async getScheduleAttendances(
    scheduleId: number,
    params?: { date?: string; class_id?: number }
  ): Promise<AttendanceRecord[]> {
    const query: Record<string, any> = {};
    if (params?.date) query.date = params.date;
    if (params?.class_id) query.class_id = params.class_id;

    const response = await ApiService.get<AttendanceRecord[]>(`/schedules/${scheduleId}/attendances`, query);
    return (response.data as any)?.data ?? response.data ?? [];
  }

  async createAttendance(data: CreateAttendanceDTO): Promise<AttendanceRecord> {
    const response = await ApiService.post<AttendanceRecord>('/attendances', data);
    return (response.data as any);
  }

  async updateAttendance(id: number, data: UpdateAttendanceDTO): Promise<AttendanceRecord> {
    const response = await ApiService.put<AttendanceRecord>(`/attendances/${id}`, data);
    return (response.data as any);
  }

  /**
   * Save attendance for an entire class in a single bulk request.
   * existingMap: Map<student_id, AttendanceRecord> for that date/subject/schedule.
   */
  async saveClassAttendance(
    entries: Array<{
      student_id: number;
      subject_id: number | null;
      teacher_id: number;
      schedule_id?: number | null;
      status: 'present' | 'absent' | 'late' | 'excused';
      reason?: string | null;
      date?: string | null;
    }>,
    existingMap: Map<number, AttendanceRecord>
  ): Promise<void> {
    const records = entries.map((entry) => {
      const existing = existingMap.get(entry.student_id);
      if (existing) {
        return { id: existing.id, status: entry.status, reason: entry.reason ?? null };
      }
      return {
        student_id:  entry.student_id,
        subject_id:  entry.subject_id,
        teacher_id:  entry.teacher_id,
        schedule_id: entry.schedule_id ?? null,
        date:        entry.date ?? null,
        status:      entry.status,
        reason:      entry.reason ?? null,
      };
    });

    await ApiService.post('/attendances/bulk', { records });
  }
}

export default new AttendanceService();
