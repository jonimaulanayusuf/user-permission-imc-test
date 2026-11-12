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
import { Level, LevelPayload } from '../../core/models';
import { LevelService } from '../../core/level.service';
import { applyServerErrors, clearServerErrors } from '../../core/form-error.util';

@Component({
  selector: 'app-level-form-dialog',
  imports: [
    ReactiveFormsModule,
    MatButtonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatProgressSpinnerModule,
    MatSlideToggleModule,
  ],
  templateUrl: './level-form-dialog.component.html',
  styleUrl: './level-form-dialog.component.css',
})
export class LevelFormDialogComponent {
  private readonly fb = inject(FormBuilder);
  private readonly levelService = inject(LevelService);
  private readonly snackBar = inject(MatSnackBar);

  readonly data = inject<Level | null>(MAT_DIALOG_DATA);
  readonly dialogRef = inject(MatDialogRef<LevelFormDialogComponent, boolean>);
  readonly saving = signal(false);

  readonly form = this.fb.nonNullable.group({
    name: [this.data?.name ?? '', [Validators.required, Validators.maxLength(50)]],
    description: [this.data?.description ?? '', [Validators.maxLength(255)]],
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
    const payload: LevelPayload = {
      name: value.name.trim(),
      description: value.description.trim() === '' ? null : value.description.trim(),
      is_active: value.is_active,
    };

    try {
      if (this.data) {
        await firstValueFrom(this.levelService.update(this.data.id, payload));
      } else {
        await firstValueFrom(this.levelService.create(payload));
      }

      this.dialogRef.close(true);
    } catch (error) {
      this.snackBar.open(applyServerErrors(this.form, error), 'Tutup', { duration: 4000 });
    } finally {
      this.saving.set(false);
    }
  }
}
