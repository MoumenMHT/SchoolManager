import ApiService from "./ApiService";
import type { ApiResponse } from "@/types";

export interface Subject {
    id: number;
    name: string;
    code: string;
    description?: string;
    created_at: string;
    updated_at: string;
}

export interface CreateSubjectDTO {
    name: string;
    code: string;
    description?: string;
}

export interface UpdateSubjectDTO {
    name?: string;
    code?: string;
    description?: string;
}

export interface SubjectCoefficient {
    id: number;
    subject_id: number;
    level_id: number;
    coefficient: number;
    weekly_sessions_required: number;
    level?: any; // Contains Level details
    subject?: Subject;
}

export interface CreateCoefficientDTO {
    subject_id: number;
    level_id: number;
    coefficient: number;
    weekly_sessions_required: number;
}

export interface UpdateCoefficientDTO {
    coefficient?: number;
    weekly_sessions_required?: number;
}


class SubjectService {
    /**
     * Get all subjects
     */
    async getSubjects(): Promise<Subject[]> {
        const response = await ApiService.get<Subject[]>('/subjects');
        return response.data || [];
    }

    /**
     * Get a single subject by ID
     */
    async getSubject(id: number): Promise<Subject> {
        const response = await ApiService.get<Subject>(`/subjects/${id}`);
        return response.data!;
    }

    /**
     * Create a new subject
     */
    async createSubject(data: CreateSubjectDTO): Promise<Subject> {
        const response = await ApiService.post<Subject>('/subjects', data);
        return response.data!;
    }

    /**
     * Update an existing subject
     */
    async updateSubject(id: number, data: UpdateSubjectDTO): Promise<Subject> {
        const response = await ApiService.put<Subject>(`/subjects/${id}`, data);
        return response.data!;
    }

    /**
     * Delete a subject
     */
    async deleteSubject(id: number): Promise<void> {
        await ApiService.delete(`/subjects/${id}`);
    }

    /**
     * Assign a subject to a teacher for a specific class and academic year
     */
    async assignSubjectToTeacher(subjectId: number, teacherId: number, classId: number, academicYear: string, coefficient: number): Promise<void> {
        await ApiService.post('/class-assignments', {
            subject_id: subjectId,
            teacher_id: teacherId,
            class_id: classId,
            academic_year: academicYear,
            coefficient
        });
    }

    /**
     * Unassign a subject from a teacher for a specific class and academic year
     */
    async unassignSubjectFromTeacher(subjectId: number, teacherId: number, classId: number, academicYear: string): Promise<void> { 
        await ApiService.delete('/class-subject-teacher', {
            data: {
                subject_id: subjectId,
                teacher_id: teacherId,
                class_id: classId,
                academic_year: academicYear
            }
        });
    }

    /**
     * get all subjects assigned to a teacher
     */
    async getSubjectsByTeacher(teacherId: number): Promise<Subject[]> {
        const response = await ApiService.get<Subject[]>(`/teachers/${teacherId}/subjects`);
        return response.data || [];
    }

    /**
     * Get all teachers who can teach a specific subject
     */
    async getTeachersBySubject(subjectId: number): Promise<any[]> {
        const response = await ApiService.get<any[]>(`/subjects/${subjectId}/teachers`);
        return response.data || [];
    }

    /**
     * Get coefficients for a subject
     */
    async getSubjectCoefficients(subjectId: number): Promise<SubjectCoefficient[]> {
        const response = await ApiService.get<SubjectCoefficient[]>(`/subjects/${subjectId}/coefficients`);
        return response.data || [];
    }

    /**
     * Create a new coefficient
     */
    async createCoefficient(data: CreateCoefficientDTO): Promise<SubjectCoefficient> {
        const response = await ApiService.post<SubjectCoefficient>('/subject-coefficients', data);
        return response.data!;
    }

    /**
     * Update an existing coefficient
     */
    async updateCoefficient(id: number, data: UpdateCoefficientDTO): Promise<SubjectCoefficient> {
        const response = await ApiService.put<SubjectCoefficient>(`/subject-coefficients/${id}`, data);
        return response.data!;
    }

    /**
     * Delete a coefficient
     */
    async deleteCoefficient(id: number): Promise<void> {
        await ApiService.delete(`/subject-coefficients/${id}`);
    }
}

export default new SubjectService();