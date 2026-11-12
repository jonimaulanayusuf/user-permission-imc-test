import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_BASE } from './api.config';
import { ApiResponse, AppUser, UserPayload } from './models';

@Injectable({ providedIn: 'root' })
export class UserService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${API_BASE}/users`;

  list(search = ''): Observable<ApiResponse<AppUser[]>> {
    const params = search === '' ? undefined : new HttpParams().set('search', search);

    return this.http.get<ApiResponse<AppUser[]>>(this.baseUrl, { params });
  }

  create(payload: UserPayload): Observable<ApiResponse<AppUser>> {
    return this.http.post<ApiResponse<AppUser>>(this.baseUrl, payload);
  }

  update(id: number, payload: UserPayload): Observable<ApiResponse<AppUser>> {
    return this.http.put<ApiResponse<AppUser>>(`${this.baseUrl}/${id}`, payload);
  }

  delete(id: number): Observable<ApiResponse<null>> {
    return this.http.delete<ApiResponse<null>>(`${this.baseUrl}/${id}`);
  }
}
