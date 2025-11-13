import { LucideIcon } from 'lucide-react';

export interface SharedData {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
        } | null;
    };
}

export interface NavItem {
    title: string;
    href?: string;
    icon?: LucideIcon;
    permission?: string;
    children?: NavItem[];
    target?: string;
}

export interface BreadcrumbItem {
    title: string;
    href?: string;
}

export interface PageAction {
    label: string;
    icon: React.ReactNode;
    variant: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
    onClick: () => void;
}

export interface TaskStage {
    id: number;
    workspace_id: number;
    name: string;
    color: string;
    order: number;
    is_default: boolean;
    tasks_count: number;
    tasks?: Task[];
    created_at: string;
    updated_at: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
    updated_at: string;
}

export interface Task {
    id: number;
    project_id: number;
    task_stage_id: number;
    milestone_id?: number;
    title: string;
    description?: string;
    priority: 'low' | 'medium' | 'high' | 'critical';
    start_date?: string;
    end_date?: string;
    assigned_to?: number;
    created_by: number;
    progress: number;
    created_at: string;
    updated_at: string;
    project?: {
        workspace?: {
            owner_id: number;
        };
    };
}

export interface TaskComment {
    id: number;
    task_id: number;
    user_id: number;
    comment: string;
    mentions?: any[];
    created_at: string;
    updated_at: string;
    user?: User;
    can_update?: boolean;
    can_delete?: boolean;
}

export interface TaskChecklist {
    id: number;
    task_id: number;
    title: string;
    is_completed: boolean;
    order: number;
    assigned_to?: User;
    due_date?: string;
    created_at: string;
    updated_at: string;
    can_update?: boolean;
    can_delete?: boolean;
}