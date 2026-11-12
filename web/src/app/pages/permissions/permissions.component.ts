import { Component, inject, OnInit, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTabsModule } from '@angular/material/tabs';
import { AuthService } from '../../core/auth.service';
import { getErrorMessage } from '../../core/form-error.util';
import {
  AppUser,
  Level,
  OverrideChoice,
  PageItem,
  UserOverride,
  UserPermissionDetail,
} from '../../core/models';
import { PermissionService } from '../../core/permission.service';
import { UserService } from '../../core/user.service';

@Component({
  selector: 'app-permissions',
  imports: [
    MatButtonModule,
    MatCardModule,
    MatCheckboxModule,
    MatFormFieldModule,
    MatIconModule,
    MatProgressSpinnerModule,
    MatSelectModule,
    MatTabsModule,
  ],
  templateUrl: './permissions.component.html',
  styleUrl: './permissions.component.css',
})
export class PermissionsComponent implements OnInit {
  private readonly permissionService = inject(PermissionService);
  private readonly userService = inject(UserService);
  private readonly auth = inject(AuthService);
  private readonly snackBar = inject(MatSnackBar);

  readonly levels = signal<Level[]>([]);
  readonly pages = signal<PageItem[]>([]);
  readonly assignments = signal<Record<number, number[]>>({});
  readonly loading = signal(true);
  readonly saving = signal(false);

  readonly users = signal<AppUser[]>([]);
  readonly selectedUserId = signal<number | null>(null);
  readonly userDetail = signal<UserPermissionDetail | null>(null);
  readonly overrides = signal<Record<number, 'allow' | 'deny'>>({});
  readonly userLoading = signal(false);
  readonly userSaving = signal(false);

  private savedAssignments: Record<number, number[]> = {};

  async ngOnInit(): Promise<void> {
    this.loading.set(true);

    try {
      await this.loadMatrix();
      await this.loadUsers();
    } catch (error) {
      this.snackBar.open(getErrorMessage(error, 'Gagal memuat data permission.'), 'Tutup', {
        duration: 4000,
      });
    } finally {
      this.loading.set(false);
    }
  }

  isChecked(levelId: number, pageId: number): boolean {
    return (this.assignments()[levelId] ?? []).includes(pageId);
  }

  toggle(levelId: number, pageId: number, checked: boolean): void {
    const current = this.assignments();
    const pagesForLevel = new Set(current[levelId] ?? []);

    if (checked) {
      pagesForLevel.add(pageId);
    } else {
      pagesForLevel.delete(pageId);
    }

    this.assignments.set({ ...current, [levelId]: [...pagesForLevel] });
  }

  async saveMatrix(): Promise<void> {
    this.saving.set(true);
    let changedLevels = 0;

    try {
      for (const level of this.levels()) {
        const current = [...(this.assignments()[level.id] ?? [])].sort((a, b) => a - b);
        const original = [...(this.savedAssignments[level.id] ?? [])].sort((a, b) => a - b);

        if (current.join(',') === original.join(',')) {
          continue;
        }

        await firstValueFrom(this.permissionService.updateLevel(level.id, current));
        changedLevels++;
      }

      if (changedLevels === 0) {
        this.snackBar.open('Tidak ada perubahan permission.', 'Tutup', { duration: 3000 });

        return;
      }

      this.savedAssignments = this.cloneAssignments(this.assignments());
      this.snackBar.open(
        `Permission ${changedLevels} level berhasil disimpan.`,
        'Tutup',
        { duration: 3000 },
      );
      await this.auth.refresh();
    } catch (error) {
      this.snackBar.open(getErrorMessage(error, 'Gagal menyimpan permission level.'), 'Tutup', {
        duration: 4000,
      });
    } finally {
      this.saving.set(false);
    }
  }

  selectUser(userId: number | null): void {
    this.selectedUserId.set(userId);
    this.userDetail.set(null);
    this.overrides.set({});

    if (userId === null) {
      return;
    }

    void this.loadUserDetail(userId);
  }

  overrideFor(pageId: number): OverrideChoice {
    return this.overrides()[pageId] ?? 'inherit';
  }

  levelHasPage(pageId: number): boolean {
    return (this.userDetail()?.level_page_ids ?? []).includes(pageId);
  }

  effectivePage(pageId: number): boolean {
    const override = this.overrides()[pageId];

    if (override === 'allow') {
      return true;
    }

    if (override === 'deny') {
      return false;
    }

    return this.levelHasPage(pageId);
  }

  setOverride(pageId: number, choice: OverrideChoice): void {
    const next = { ...this.overrides() };

    if (choice === 'inherit') {
      delete next[pageId];
    } else {
      next[pageId] = choice;
    }

    this.overrides.set(next);
  }

  async saveUser(): Promise<void> {
    const detail = this.userDetail();

    if (!detail) {
      return;
    }

    this.userSaving.set(true);

    const overrides: UserOverride[] = Object.entries(this.overrides()).map(([pageId, effect]) => ({
      page_id: Number(pageId),
      effect: effect as 'allow' | 'deny',
    }));

    try {
      const response = await firstValueFrom(
        this.permissionService.updateUser(detail.user.id, overrides),
      );

      this.userDetail.set(response.data);
      this.syncOverrides(response.data);
      this.snackBar.open('Override permission user berhasil disimpan.', 'Tutup', {
        duration: 3000,
      });

      if (this.auth.user()?.id === detail.user.id) {
        await this.auth.refresh();
      }
    } catch (error) {
      this.snackBar.open(getErrorMessage(error, 'Gagal menyimpan override permission.'), 'Tutup', {
        duration: 4000,
      });
    } finally {
      this.userSaving.set(false);
    }
  }

  private async loadMatrix(): Promise<void> {
    const response = await firstValueFrom(this.permissionService.getMatrix());
    const assignmentMap: Record<number, number[]> = {};

    for (const level of response.data.levels) {
      assignmentMap[level.id] = [];
    }

    for (const assignment of response.data.level_permissions) {
      if (!assignmentMap[assignment.level_id]) {
        assignmentMap[assignment.level_id] = [];
      }

      assignmentMap[assignment.level_id].push(assignment.page_id);
    }

    this.levels.set(response.data.levels);
    this.pages.set(response.data.pages);
    this.assignments.set(assignmentMap);
    this.savedAssignments = this.cloneAssignments(assignmentMap);
  }

  private async loadUsers(): Promise<void> {
    const response = await firstValueFrom(this.userService.list());
    this.users.set(response.data);
  }

  private async loadUserDetail(userId: number): Promise<void> {
    this.userLoading.set(true);

    try {
      const response = await firstValueFrom(this.permissionService.getUserDetail(userId));
      this.userDetail.set(response.data);
      this.syncOverrides(response.data);
    } catch (error) {
      this.snackBar.open(getErrorMessage(error, 'Gagal memuat permission user.'), 'Tutup', {
        duration: 4000,
      });
    } finally {
      this.userLoading.set(false);
    }
  }

  private syncOverrides(detail: UserPermissionDetail): void {
    const overrideMap: Record<number, 'allow' | 'deny'> = {};

    for (const override of detail.overrides) {
      overrideMap[override.page_id] = override.effect;
    }

    this.overrides.set(overrideMap);
  }

  private cloneAssignments(source: Record<number, number[]>): Record<number, number[]> {
    const copy: Record<number, number[]> = {};

    Object.entries(source).forEach(([levelId, pageIds]) => {
      copy[Number(levelId)] = [...pageIds];
    });

    return copy;
  }
}
