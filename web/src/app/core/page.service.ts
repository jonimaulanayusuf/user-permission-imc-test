import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_BASE } from './api.config';
import { ApiResponse, PageItem, PagePayload } from './models';

@Injectable({ providedIn: 'root' })
export class PageService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${API_BASE}/pages`;

  list(): Observable<ApiResponse<PageItem[]>> {
    return this.http.get<ApiResponse<PageItem[]>>(this.baseUrl);
  }

  create(payload: PagePayload): Observable<ApiResponse<PageItem>> {
    return this.http.post<ApiResponse<PageItem>>(this.baseUrl, payload);
  }

  update(id: number, payload: PagePayload): Observable<ApiResponse<PageItem>> {
    return this.http.put<ApiResponse<PageItem>>(`${this.baseUrl}/${id}`, payload);
  }

  delete(id: number): Observable<ApiResponse<null>> {
    return this.http.delete<ApiResponse<null>>(`${this.baseUrl}/${id}`);
  }
}
