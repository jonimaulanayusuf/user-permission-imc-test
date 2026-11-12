export interface ApiResponse<T> {
  success: true;
  data: T;
  message?: string;
}

export interface ApiErrorBody {
  success: false;
  message: string;
  errors?: Record<string, string[]>;
}

export interface Level {
  id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  user_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface PageItem {
  id: number;
  name: string;
  path: string;
  description: string | null;
  sort_order: number;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface AppUser {
  id: number;
  full_name: string;
  username: string;
  email: string;
  level: {
    id: number;
    name: string;
    is_active: boolean;
  };
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface MenuItem {
  id: number;
  name: string;
  path: string;
  description: string | null;
  sort_order: number;
}

export interface SessionPayload {
  user: AppUser;
  permissions: string[];
  menus: MenuItem[];
}

export interface LoginPayload extends SessionPayload {
  token: string;
}

export interface LevelPermissionAssignment {
  level_id: number;
  page_id: number;
}

export interface PermissionMatrix {
  levels: Level[];
  pages: PageItem[];
  level_permissions: LevelPermissionAssignment[];
}

export interface UserOverride {
  page_id: number;
  effect: 'allow' | 'deny';
}

export interface UserPermissionDetail {
  user: {
    id: number;
    full_name: string;
    username: string;
    level_id: number;
    level_name: string;
  };
  pages: PageItem[];
  level_page_ids: number[];
  overrides: UserOverride[];
  effective_page_ids: number[];
}

export type OverrideChoice = 'inherit' | 'allow' | 'deny';

export interface UserPayload {
  full_name: string;
  username: string;
  email: string;
  password?: string;
  level_id: number;
  is_active: boolean;
}

export interface LevelPayload {
  name: string;
  description: string | null;
  is_active: boolean;
}

export interface PagePayload {
  name: string;
  path: string;
  description: string | null;
  sort_order: number;
  is_active: boolean;
}

export interface ConfirmDialogData {
  title: string;
  message: string;
  confirmLabel?: string;
}
