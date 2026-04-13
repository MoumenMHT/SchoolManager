import ApiService from "./ApiService";
import type { ApiResponse } from "@/types";

export interface Level {
    id: number;
    name: string;
    cycle: string;
    year_number: number;
    track: string | null;
    sort_order: number;
    is_active: boolean;
}

class LevelService {
    /**
     * Get all levels
     */
    async getLevels(): Promise<Level[]> {
        const response = await ApiService.get<Level[]>('/levels');
        return response.data || [];
    }
}

export default new LevelService();
