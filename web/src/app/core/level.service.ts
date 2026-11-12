import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_BASE } from './api.config';
import { ApiResponse, Level, LevelPayload } from './models';

@Injectable({ providedIn: 'root' })
export class LevelService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${API_BASE}/levels`;

  list(): Observable<ApiResponse<Level[]>> {
    return this.http.get<ApiResponse<Level[]>>(this.baseUrl);
  }

  create(payload: LevelPayload): Observable<ApiResponse<Level>> {
    return this.http.post<ApiResponse<Level>>(this.baseUrl, payload);
  }

  update(id: number, payload: LevelPayload): Observable<ApiResponse<Level>> {
    return this.http.put<ApiResponse<Level>>(`${this.baseUrl}/${id}`, payload);
  }

  delete(id: number): Observable<ApiResponse<null>> {
    return this.http.delete<ApiResponse<null>>(`${this.baseUrl}/${id}`);
  }
}
