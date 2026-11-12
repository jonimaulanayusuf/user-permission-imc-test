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
import { ConfirmDialogData, PageItem } from '../../core/models';
import { PageService } from '../../core/page.service';
import { getErrorMessage } from '../../core/form-error.util';
import { ConfirmDialogComponent } from '../../shared/confirm-dialog.component';
import { PageFormDialogComponent } from './page-form-dialog.component';

@Component({
  selector: 'app-page-list',
  imports: [
    MatButtonModule,
    MatCardModule,
    MatIconModule,
    MatProgressSpinnerModule,
    MatTableModule,
    MatTooltipModule,
  ],
  templateUrl: './page-list.component.html',
  styleUrl: './page-list.component.css',
})
export class PageListComponent implements OnInit {
  private readonly pageService = inject(PageService);
  private readonly dialog = inject(MatDialog);
  private readonly snackBar = inject(MatSnackBar);

  readonly pages = signal<PageItem[]>([]);
  readonly loading = signal(false);
  readonly columns = ['name', 'path', 'sort_order', 'status', 'actions'];

  async ngOnInit(): Promise<void> {
    await this.load();
  }

  async load(): Promise<void> {
    this.loading.set(true);

    try {
      const response = await firstValueFrom(this.pageService.list());
      this.pages.set(response.data);
    } catch (error) {
      this.snackBar.open(getErrorMessage(error, 'Gagal memuat data page.'), 'Tutup', {
        duration: 4000,
      });
    } finally {
      this.loading.set(false);
    }
  }

  openCreate(): void {
    const dialogRef = this.dialog.open<PageFormDialogComponent, PageItem | null, boolean>(
      PageFormDialogComponent,
      {
        width: '620px',
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

  openEdit(page: PageItem): void {
    const dialogRef = this.dialog.open<PageFormDialogComponent, PageItem | null, boolean>(
      PageFormDialogComponent,
      {
        width: '620px',
        maxWidth: '95vw',
        data: page,
        disableClose: true,
      },
    );

    dialogRef.afterClosed().subscribe((saved) => {
      if (saved) {
        void this.load();
      }
    });
  }

  async remove(page: PageItem): Promise<void> {
    const dialogRef = this.dialog.open<ConfirmDialogComponent, ConfirmDialogData, boolean>(
      ConfirmDialogComponent,
      {
        width: '420px',
        data: {
          title: 'Hapus Page',
          message: `Yakin ingin menghapus page "${page.name}" (${page.path})? Permission level dan user yang terkait juga akan dihapus.`,
          confirmLabel: 'Hapus',
        },
      },
    );

    if (!(await firstValueFrom(dialogRef.afterClosed()))) {
      return;
    }

    try {
      await firstValueFrom(this.pageService.delete(page.id));
      this.snackBar.open('Page berhasil dihapus.', 'Tutup', { duration: 3000 });
      await this.load();
    } catch (error) {
      this.snackBar.open(getErrorMessage(error, 'Gagal menghapus page.'), 'Tutup', {
        duration: 4000,
      });
    }
  }
}
