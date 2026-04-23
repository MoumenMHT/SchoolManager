import ApiService from "./ApiService";
import type { ApiResponse } from "@/types";
import type { Subject } from "./SubjectService";

export interface Level {
    id?: number;
    name: string;
    cycle: string;
    year_number: number;
    track: string | null;
    sort_order: number;
    is_active: boolean;
}

export interface LevelSubjectMapping {
    subject_id: number;
    coefficient: number;
    weekly_sessions_required?: number | null;
    weekly_hours?: number | null;
    subject?: Subject;
    pivot?: {
        coefficient: number;
        weekly_sessions_required?: number;
        weekly_hours?: number;
    };
}

class LevelService {
    /**
     * Get all levels
     */
    async getLevels(): Promise<Level[]> {
        const response = await ApiService.get<Level[]>('/levels');
        return response.data || [];
    }

    /**
     * Create a new level
     */
    async createLevel(level: Partial<Level>): Promise<Level> {
        const response = await ApiService.post<Level>('/levels', level);
        return response.data;
    }

    /**
     * Update an existing level
     */
    async updateLevel(id: number, level: Partial<Level>): Promise<Level> {
        const response = await ApiService.put<Level>(`/levels/${id}`, level);
        return response.data;
    }

    /**
     * Delete a level
     */
    async deleteLevel(id: number): Promise<void> {
        await ApiService.delete(`/levels/${id}`);
    }

    /**
     * Get subjects mapped to a level
     */
    async getLevelSubjects(id: number): Promise<LevelSubjectMapping[]> {
        const response = await ApiService.get<LevelSubjectMapping[]>(`/levels/${id}/subjects`);
        return response.data || [];
    }

    /**
     * Assign subjects to a level
     */
    async assignSubjects(id: number, subjects: any[]): Promise<any> {
        const response = await ApiService.post(`/levels/${id}/assign-subjects`, { subjects });
        return response.data;
    }
}

export default new LevelService();
