export interface User { id: string; name: string; email: string; title?: string; role?: string }
export interface PageProps { auth: { user: User | null }; flash: { success?: string; error?: string } }
export interface Project { id: string; name: string; code: string; description?: string; status: string; progress: number; due_at?: string; tasks_count?: number; completed_tasks_count?: number }
export interface WorkRequest { id: string; reference: string; title: string; status: string; priority: string; requested_by?: User; current_step?: number; submitted_at?: string }
export interface Workflow { id: string; name: string; description?: string; status: string; versions_count?: number; runs_count?: number }
export interface Todo { id: string; title: string; completed_at?: string; due_at?: string; priority: string }
