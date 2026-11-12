import { Component, inject, OnInit, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';
import { ConfirmDialogData, Level } from '../../core/models';
import { LevelService } from '../../core/level.service';
import { getErrorMessage } from '../../core/form-error.util';
import { ConfirmDialogComponent } from '../../shared/confirm-dialog.component';
import { LevelFormDialogComponent } from './level-form-dialog.component';

@Component({
  selector: 'app-level-list',
  imports: [
    MatButtonModule,
    MatCardModule,
    MatIconModule,
    MatProgressSpinnerModule,
    MatTableModule,
    MatTooltipModule,
  ],
  templateUrl: './level-list.component.html',
  styleUrl: './level-list.component.css',
})
export class LevelListComponent implements OnInit {
  private readonly levelService = inject(LevelService);
  private readonly dialog = inject(MatDialog);
  private readonly snackBar = inject(MatSnackBar);

  readonly levels = signal<Level[]>([]);
  readonly loading = signal(false);
  readonly columns = ['name', 'description', 'users', 'status', 'actions'];

  async ngOnInit(): Promise<void> {
    await this.load();
  }

  async load(): Promise<void> {
    this.loading.set(true);

    try {
      const response = await firstValueFrom(this.levelService.list());
      this.levels.set(response.data);
    } catch (error) {
      this.snackBar.open(getErrorMessage(error, 'Gagal memuat data level.'), 'Tutup', {
        duration: 4000,
      });
    } finally {
      this.loading.set(false);
    }
  }

  openCreate(): void {
    const dialogRef = this.dialog.open<LevelFormDialogComponent, Level | null, boolean>(
      LevelFormDialogComponent,
      {
        width: '520px',
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

  openEdit(level: Level): void {
    const dialogRef = this.dialog.open<LevelFormDialogComponent, Level | null, boolean>(
      LevelFormDialogComponent,
      {
        width: '520px',
        maxWidth: '95vw',
        data: level,
        disableClose: true,
      },
    );

    dialogRef.afterClosed().subscribe((saved) => {
      if (saved) {
        void this.load();
      }
    });
  }

  async remove(level: Level): Promise<void> {
    const dialogRef = this.dialog.open<ConfirmDialogComponent, ConfirmDialogData, boolean>(
      ConfirmDialogComponent,
      {
        width: '420px',
        data: {
          title: 'Hapus Level',
          message: `Yakin ingin menghapus level "${level.name}"?`,
          confirmLabel: 'Hapus',
        },
      },
    );

    if (!(await firstValueFrom(dialogRef.afterClosed()))) {
      return;
    }

    try {
      await firstValueFrom(this.levelService.delete(level.id));
      this.snackBar.open('Level berhasil dihapus.', 'Tutup', { duration: 3000 });
      await this.load();
    } catch (error) {
      this.snackBar.open(getErrorMessage(error, 'Gagal menghapus level.'), 'Tutup', {
        duration: 4000,
      });
    }
  }
}
