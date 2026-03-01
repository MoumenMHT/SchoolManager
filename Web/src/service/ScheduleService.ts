import ApiService from './ApiService';

export interface Schedule {
  id: number;
  class_subject_teacher_id: number;
  day: string;
  start_time: string;
  end_time: string;
  room: string | null;
  academic_year: string | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
  class?: {
    id: number;
    name: string;
    level: string;
  };
  subject?: {
    id: number;
    name: string;
  };
  teacher?: {
    id: number;
    name: string;
    email: string;
  };
    assignment?: {
    id: number;
    teacher_id: number;
    subject_id: number;
    class_id: number;
    teacher?: {
      id: number;
      first_name: string;
      last_name: string;
    };
    subject?: {
      id: number;
      name: string;
    };
    class?: {
      id: number;
      name: string;
      level?: string;
    };
  };
  
}

export interface CreateScheduleDTO {
  class_subject_teacher_id: number;
  day: string;
  start_time: string;
  end_time: string;
  room?: string | null;
  academic_year?: string | null;
  notes?: string | null;
}

export interface UpdateScheduleDTO extends Partial<CreateScheduleDTO> {}

export interface ClassScheduleResponse {
  data: {
    [day: string]: Schedule[];
  };
}

class ScheduleService {
  private api = ApiService;

  /**
   * Get all schedules with optional filters
   */
  async getSchedules(filters?: any): Promise<Schedule[]> {
    const response = await this.api.get('/schedules', { params: filters });
    return (response.data as any).data || response.data as Schedule[];
  }

  /**
   * Get class schedule (weekly view)
   */
  async getClassSchedule(classId: number, academic_year?: any): Promise<any> {
    const response = await this.api.get(`/classes/${classId}/schedule`, { academic_year });

    return (response.data as any).data || response.data;
  }

  /**
   * Get a single schedule by ID
   */
  async getSchedule(id: number): Promise<Schedule> {
    const response = await this.api.get(`/schedules/${id}`);
    return (response.data as any).data;
  }

  /**
   * Create a new schedule
   */
  async createSchedule(data: CreateScheduleDTO): Promise<Schedule> {
    const response = await this.api.post('/schedules', data);
    return (response.data as any).data;
  }

  /**
   * Update an existing schedule
   */
  async updateSchedule(id: number, data: UpdateScheduleDTO): Promise<Schedule> {
    const response = await this.api.put(`/schedules/${id}`, data);
    return (response.data as any).data;
  }

  /**
   * Delete a schedule
   */
  async deleteSchedule(id: number): Promise<void> {
    await this.api.delete(`/schedules/${id}`);
  }

  /**
   * Bulk create schedules
   */
  async bulkCreateSchedules(schedules: CreateScheduleDTO[]): Promise<any> {
    const response = await this.api.post('/schedules/bulk', { schedules });
    return response.data;
  }

  /**
   * Check for schedule conflicts
   */
  async checkConflicts(data: CreateScheduleDTO): Promise<any> {
    const response = await this.api.post('/schedules/check-conflicts', data);
    return response.data;
  }

  /**
   * Get teacher schedule
   */
  async getTeacherSchedule(teacherId: number, academic_year?: any): Promise<ClassScheduleResponse> {
    const response = await this.api.get(`/teachers/${teacherId}/schedule`, { academic_year  });
    return response.data as ClassScheduleResponse;
  }

  /**
   * Get available time slots
   */
  async getAvailableSlots(filters?: any): Promise<any> {
    const response = await this.api.get('/schedules/available-slots', { params: filters });
    return response.data;
  }

  /**
   * Get weekly overview
   */
  async getWeeklyOverview(filters?: any): Promise<any> {
    const response = await this.api.get('/schedules/weekly-overview', { params: filters });
    return response.data;
  }

  /**
   * Get student schedule (through their class)
   */
  async getStudentSchedule(classId: number, academic_year?: any): Promise<any> {
    const response = await this.api.get(`/classes/${classId}/schedule`, { academic_year });
    console.log(`Fetched schedule for class ID ${classId} and academic year ${academic_year}:`, response.data);
    return (response.data as any).data || response.data;
  }
}

export default new ScheduleService();
