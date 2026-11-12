import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatDialog } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';
import { AppUser, ConfirmDialogData } from '../../core/models';
import { AuthService } from '../../core/auth.service';
import { UserService } from '../../core/user.service';
import { getErrorMessage } from '../../core/form-error.util';
import { ConfirmDialogComponent } from '../../shared/confirm-dialog.component';
import { UserFormDialogComponent } from './user-form-dialog.component';

@Component({
  selector: 'app-user-list',
  imports: [
    MatButtonModule,
    MatCardModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    MatProgressSpinnerModule,
    MatTableModule,
    MatTooltipModule,
  ],
  templateUrl: './user-list.component.html',
  styleUrl: './user-list.component.css',
})
export class UserListComponent implements OnInit {
  private readonly userService = inject(UserService);
  private readonly auth = inject(AuthService);
  private readonly dialog = inject(MatDialog);
  private readonly snackBar = inject(MatSnackBar);

  readonly users = signal<AppUser[]>([]);
  readonly loading = signal(false);
  readonly search = signal('');
  readonly columns = ['name', 'username', 'email', 'level', 'status', 'actions'];
  readonly currentUserId = computed(() => this.auth.user()?.id ?? null);

  readonly filteredUsers = computed(() => {
    const term = this.search().trim().toLowerCase();

    if (term === '') {
      return this.users();
    }

    return this.users().filter(
      (user) =>
        user.full_name.toLowerCase().includes(term) ||
        user.username.toLowerCase().includes(term) ||
        user.email.toLowerCase().includes(term) ||
        user.level.name.toLowerCase().includes(term),
    );
  });

  async ngOnInit(): Promise<void> {
    await this.load();
  }

  async load(): Promise<void> {
    this.loading.set(true);

    try {
      const response = await firstValueFrom(this.userService.list());
      this.users.set(response.data);
    } catch (error) {
      this.snackBar.open(getErrorMessage(error, 'Gagal memuat data user.'), 'Tutup', {
        duration: 4000,
      });
    } finally {
      this.loading.set(false);
    }
  }

  openCreate(): void {
    const dialogRef = this.dialog.open<UserFormDialogComponent, AppUser | null, boolean>(
      UserFormDialogComponent,
      {
        width: '640px',
        maxWidth: '95vw',
        data: null,
        disableClose: true,
      },
    );

    dialogRef.afterClosed().subscribe((saved) => {
      if (saved) {
        void this.load();
      }
    });
  }

  openEdit(user: AppUser): void {
    const dialogRef = this.dialog.open<UserFormDialogComponent, AppUser | null, boolean>(
      UserFormDialogComponent,
      {
        width: '640px',
        maxWidth: '95vw',
        data: user,
        disableClose: true,
      },
    );

    dialogRef.afterClosed().subscribe((saved) => {
      if (saved) {
        void this.load();
      }
    });
  }

  async remove(user: AppUser): Promise<void> {
    const dialogRef = this.dialog.open<ConfirmDialogComponent, ConfirmDialogData, boolean>(
      ConfirmDialogComponent,
      {
        width: '420px',
        data: {
          title: 'Hapus User',
          message: `Yakin ingin menghapus user "${user.full_name}"? Tindakan ini tidak dapat dibatalkan.`,
          confirmLabel: 'Hapus',
        },
      },
    );

    if (!(await firstValueFrom(dialogRef.afterClosed()))) {
      return;
    }

    try {
      await firstValueFrom(this.userService.delete(user.id));
      this.snackBar.open('User berhasil dihapus.', 'Tutup', { duration: 3000 });
      await this.load();
    } catch (error) {
      this.snackBar.open(getErrorMessage(error, 'Gagal menghapus user.'), 'Tutup', {
        duration: 4000,
      });
    }
  }
}
