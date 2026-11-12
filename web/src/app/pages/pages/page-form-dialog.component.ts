import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatSnackBar } from '@angular/material/snack-bar';
import { firstValueFrom } from 'rxjs';
import { PageItem, PagePayload } from '../../core/models';
import { PageService } from '../../core/page.service';
import { applyServerErrors, clearServerErrors } from '../../core/form-error.util';

@Component({
  selector: 'app-page-form-dialog',
  imports: [
    ReactiveFormsModule,
    MatButtonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatProgressSpinnerModule,
    MatSlideToggleModule,
  ],
  templateUrl: './page-form-dialog.component.html',
  styleUrl: './page-form-dialog.component.css',
})
export class PageFormDialogComponent {
  private readonly fb = inject(FormBuilder);
  private readonly pageService = inject(PageService);
  private readonly snackBar = inject(MatSnackBar);

  readonly data = inject<PageItem | null>(MAT_DIALOG_DATA);
  readonly dialogRef = inject(MatDialogRef<PageFormDialogComponent, boolean>);
  readonly saving = signal(false);

  readonly form = this.fb.nonNullable.group({
    name: [this.data?.name ?? '', [Validators.required, Validators.maxLength(100)]],
    path: [this.data?.path ?? '', [Validators.required, Validators.maxLength(150)]],
    description: [this.data?.description ?? '', [Validators.maxLength(255)]],
    sort_order: [this.data?.sort_order ?? 0, [Validators.required, Validators.min(0)]],
    is_active: [this.data?.is_active ?? true],
  });

  async save(): Promise<void> {
    if (this.form.invalid) {
      this.form.markAllAsTouched();

      return;
    }

    this.saving.set(true);
    clearServerErrors(this.form);

    const value = this.form.getRawValue();
    const payload: PagePayload = {
      name: value.name.trim(),
      path: value.path.trim(),
      description: value.description.trim() === '' ? null : value.description.trim(),
      sort_order: value.sort_order,
      is_active: value.is_active,
    };

    try {
      if (this.data) {
        await firstValueFrom(this.pageService.update(this.data.id, payload));
      } else {
        await firstValueFrom(this.pageService.create(payload));
      }

      this.dialogRef.close(true);
    } catch (error) {
      this.snackBar.open(applyServerErrors(this.form, error), 'Tutup', { duration: 4000 });
    } finally {
      this.saving.set(false);
    }
  }
}
