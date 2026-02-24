// Student Interface
export interface Student {
  id: number;
  code: string;
  first_name: string;
  last_name: string;
  birth_date: string;
  gender: 'male' | 'female';
  class_id: number;
  parent_id: number;
  enrollment_date: string;
  medical_info?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  class?: SchoolClass;
  parent?: Parent;
}

// Teacher Interface
export interface Teacher {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  cin: string;
  birth_date: string;
  specialization: string;
  hire_date: string;
  salary: number;
  created_at: string;
  updated_at: string;
  user?: User;
}

// Class Interface
export interface SchoolClass {
  id: number;
  name: string;
  level: string;
  academic_year: string;
  capacity: number;
  main_teacher_id?: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  main_teacher?: Teacher;
  students_count?: number;
}

// Subject Interface
export interface Subject {
  id: number;
  name: string;
  code: string;
  description?: string;
  hours_per_week: number;
  created_at: string;
  updated_at: string;
}

// Grade Interface
export interface Grade {
  id: number;
  student_id: number;
  subject_id: number;
  teacher_id: number;
  semester: string;
  academic_year: string;
  grade: number;
  max_grade: number;
  date: string;
  type: 'homework' | 'quiz' | 'exam' | 'project';
  notes?: string;
  created_at: string;
  updated_at: string;
  student?: Student;
  subject?: Subject;
  teacher?: Teacher;
}

// Attendance Interface
export interface Attendance {
  id: number;
  student_id: number;
  class_id: number;
  date: string;
  status: 'present' | 'absent' | 'late' | 'excused';
  notes?: string;
  created_at: string;
  updated_at: string;
  student?: Student;
  class?: SchoolClass;
}

// Payment Interface
export interface Payment {
  id: number;
  student_id: number;
  amount: number;
  paid_amount: number;
  status: 'pending' | 'paid' | 'late' | 'cancelled';
  payment_type: 'tuition' | 'transport' | 'lunch' | 'other';
  due_date: string;
  paid_date?: string;
  academic_year: string;
  semester?: string;
  notes?: string;
  created_at: string;
  updated_at: string;
  student?: Student;
}

// User Interface
export interface User {
  id: number;
  username: string;
  email: string;
  role: 'admin' | 'teacher' | 'parent';
  phone?: string;
  address?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

// Parent Interface
export interface Parent {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  cin: string;
  relationship: 'father' | 'mother' | 'guardian';
  occupation?: string;
  created_at: string;
  updated_at: string;
  user?: User;
}

// Dashboard Stats Interface
export interface DashboardStats {
  overview: {
    total_students: number;
    total_teachers: number;
    total_classes: number;
  };
  financial: {
    total_revenue: number;
    pending_payments: number;
    late_payments: number;
    payment_rate: number;
  };
  academic: {
    average_grade: number;
    attendance_rate: number;
  };
  attendance_breakdown: {
    present?: number;
    absent?: number;
    late?: number;
    excused?: number;
  };
  students_by_class: {
    class_name: string;
    student_count: number;
  }[];
  recent_payments: Payment[];
}

// API Response Interface
export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
  error?: Record<string, string[]>;

}

// Auth Response Interface
export interface AuthResponse {
  success: boolean;
  token: string;
  token_type: string;
  user: User;
}

// Pagination Interface
export interface PaginatedResponse<T> {
  success: boolean;
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
