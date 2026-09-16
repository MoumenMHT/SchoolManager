import ApiService from "./ApiService";

export interface AcademicYear {
    id?: number;
    name: string;
    start_date?: string;
    end_date?: string;
    is_current?: boolean;
    status?: string;
}

class AcademicYearService {
    /**
     * Get all academic years
     */
    async getAcademicYears(): Promise<AcademicYear[]> {
        try {
            const response = await ApiService.get<AcademicYear[]>('/academic-years');
            if (Array.isArray(response)) {
                return response;
            }
            return (response as any)?.data || [];
        } catch (error) {
            console.error("Failed to fetch academic years:", error);
            return [];
        }
    }

    /**
     * Get array of academic year names (e.g. ["2025-2026", "2026-2027"])
     */
    async getAcademicYearNames(): Promise<string[]> {
        const years = await this.getAcademicYears();
        return years.map(y => y.name);
    }

    /**
     * Get the active current academic year name from database (where is_current is true).
     */
    async getCurrentAcademicYear(): Promise<string> {
        try {
            const years = await this.getAcademicYears();
            const current = years.find(y => Boolean(y.is_current) || (y as any).is_current === 1 || (y as any).is_current === '1');
            if (current && current.name) {
                return current.name;
            }
            if (years.length > 0 && years[0].name) {
                return years[0].name;
            }
        } catch (error) {
            console.error("Failed to fetch active academic year:", error);
        }

        const now = new Date();
        const year = now.getFullYear();
        return now.getMonth() >= 8 ? `${year}-${year + 1}` : `${year - 1}-${year}`;
    }

    /**
     * Get the active current academic year object from database.
     */
    async getCurrentAcademicYearObject(): Promise<AcademicYear | null> {
        try {
            const years = await this.getAcademicYears();
            const current = years.find(y => Boolean(y.is_current) || (y as any).is_current === 1 || (y as any).is_current === '1');
            if (current) return current;
            return years[0] || null;
        } catch (error) {
            console.error("Failed to fetch active academic year object:", error);
            return null;
        }
    }
}

export default new AcademicYearService();
