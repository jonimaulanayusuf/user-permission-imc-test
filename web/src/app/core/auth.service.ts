import { computed, inject, Injectable, signal } from '@angular/core';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { API_BASE } from './api.config';
import { ApiResponse, AppUser, LoginPayload, MenuItem, SessionPayload } from './models';

const TOKEN_KEY = 'imc_access_token';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  private readonly userSignal = signal<AppUser | null>(null);
  private readonly permissionsSignal = signal<string[]>([]);
  private readonly menusSignal = signal<MenuItem[]>([]);

  private loadingPromise: Promise<boolean> | null = null;

  readonly user = this.userSignal.asReadonly();
  readonly permissions = this.permissionsSignal.asReadonly();
  readonly menus = this.menusSignal.asReadonly();
  readonly isLoggedIn = computed(() => this.userSignal() !== null);

  get token(): string | null {
    return localStorage.getItem(TOKEN_KEY);
  }

  async login(username: string, password: string): Promise<void> {
    const response = await firstValueFrom(
      this.http.post<ApiResponse<LoginPayload>>(`${API_BASE}/auth/login`, { username, password }),
    );

    localStorage.setItem(TOKEN_KEY, response.data.token);
    this.applySession(response.data);
  }

  async ensureLoaded(): Promise<boolean> {
    if (this.userSignal() !== null) {
      return true;
    }

    if (!this.token) {
      return false;
    }

    let promise = this.loadingPromise;

    if (promise === null) {
      promise = this.loadSession().finally(() => {
        this.loadingPromise = null;
      });

      this.loadingPromise = promise;
    }

    return promise;
  }

  async refresh(): Promise<void> {
    if (!this.token) {
      return;
    }

    const response = await firstValueFrom(
      this.http.get<ApiResponse<SessionPayload>>(`${API_BASE}/auth/me`),
    );

    this.applySession(response.data);
  }

  hasPermission(path: string): boolean {
    return this.permissionsSignal().includes(path);
  }

  logout(redirect = true): void {
    localStorage.removeItem(TOKEN_KEY);
    this.userSignal.set(null);
    this.permissionsSignal.set([]);
    this.menusSignal.set([]);

    if (redirect) {
      void this.router.navigate(['/login']);
    }
  }

  private async loadSession(): Promise<boolean> {
    try {
      const response = await firstValueFrom(
        this.http.get<ApiResponse<SessionPayload>>(`${API_BASE}/auth/me`),
      );
      this.applySession(response.data);

      return true;
    } catch {
      this.logout(false);

      return false;
    }
  }

  private applySession(payload: SessionPayload): void {
    this.userSignal.set(payload.user);
    this.permissionsSignal.set(payload.permissions);
    this.menusSignal.set(payload.menus);
  }
}
