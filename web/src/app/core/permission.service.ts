import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_BASE } from './api.config';
import { ApiResponse, PermissionMatrix, UserOverride, UserPermissionDetail } from './models';

@Injectable({ providedIn: 'root' })
export class PermissionService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${API_BASE}/permissions`;

  getMatrix(): Observable<ApiResponse<PermissionMatrix>> {
    return this.http.get<ApiResponse<PermissionMatrix>>(this.baseUrl);
  }

  updateLevel(
    levelId: number,
    pageIds: number[],
  ): Observable<ApiResponse<{ level_id: number; page_ids: number[] }>> {
    return this.http.put<ApiResponse<{ level_id: number; page_ids: number[] }>>(
      `${this.baseUrl}/levels/${levelId}`,
      { page_ids: pageIds },
    );
  }

  getUserDetail(userId: number): Observable<ApiResponse<UserPermissionDetail>> {
    return this.http.get<ApiResponse<UserPermissionDetail>>(`${this.baseUrl}/users/${userId}`);
  }

  updateUser(
    userId: number,
    overrides: UserOverride[],
  ): Observable<ApiResponse<UserPermissionDetail>> {
    return this.http.put<ApiResponse<UserPermissionDetail>>(`${this.baseUrl}/users/${userId}`, {
      overrides,
    });
  }
}
