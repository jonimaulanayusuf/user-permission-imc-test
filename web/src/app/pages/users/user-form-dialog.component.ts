import { Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatSnackBar } from '@angular/material/snack-bar';
import { firstValueFrom } from 'rxjs';
import { AppUser, Level, UserPayload } from '../../core/models';
import { LevelService } from '../../core/level.service';
import { UserService } from '../../core/user.service';
import { applyServerErrors, clearServerErrors } from '../../core/form-error.util';

@Component({
  selector: 'app-user-form-dialog',
  imports: [
    ReactiveFormsModule,
    MatButtonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatProgressSpinnerModule,
    MatSelectModule,
    MatSlideToggleModule,
  ],
  templateUrl: './user-form-dialog.component.html',
  styleUrl: './user-form-dialog.component.css',
})
export class UserFormDialogComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly userService = inject(UserService);
  private readonly levelService = inject(LevelService);
  private readonly snackBar = inject(MatSnackBar);

  readonly data = inject<AppUser | null>(MAT_DIALOG_DATA);
  readonly dialogRef = inject(MatDialogRef<UserFormDialogComponent, boolean>);

  readonly levels = signal<Level[]>([]);
  readonly loadingLevels = signal(true);
  readonly saving = signal(false);

  readonly form = this.fb.group({
    full_name: this.fb.nonNullable.control('', [
      Validators.required,
      Validators.maxLength(100),
    ]),
    username: this.fb.nonNullable.control('', [
      Validators.required,
      Validators.minLength(3),
      Validators.maxLength(50),
      Validators.pattern(/^[a-zA-Z0-9._-]+$/),
    ]),
    email: this.fb.nonNullable.control('', [
      Validators.required,
      Validators.email,
      Validators.maxLength(150),
    ]),
    password: this.fb.nonNullable.control(
      '',
      this.data
        ? [Validators.minLength(8), Validators.maxLength(72)]
        : [Validators.required, Validators.minLength(8), Validators.maxLength(72)],
    ),
    level_id: this.fb.control<number | null>(this.data?.level.id ?? null, [Validators.required]),
    is_active: this.fb.nonNullable.control(this.data?.is_active ?? true),
  });

  async ngOnInit(): Promise<void> {
    if (this.data) {
      this.form.patchValue({
        full_name: this.data.full_name,
        username: this.data.username,
        email: this.data.email,
        level_id: this.data.level.id,
        is_active: this.data.is_active,
      });
    }

    try {
      const response = await firstValueFrom(this.levelService.list());
      this.levels.set(response.data);
    } catch (error) {
      this.snackBar.open(applyServerErrors(this.form, error), 'Tutup', { duration: 4000 });
    } finally {
      this.loadingLevels.set(false);
    }
  }

  async save(): Promise<void> {
    if (this.form.invalid) {
      this.form.markAllAsTouched();

      return;
    }

    const value = this.form.getRawValue();
    const levelId = value.level_id;

    if (levelId === null) {
      return;
    }

    this.saving.set(true);
    clearServerErrors(this.form);

    const payload: UserPayload = {
      full_name: value.full_name.trim(),
      username: value.username.trim(),
      email: value.email.trim(),
      level_id: levelId,
      is_active: value.is_active,
    };

    if (value.password.trim() !== '') {
      payload.password = value.password;
    }

    try {
      if (this.data) {
        await firstValueFrom(this.userService.update(this.data.id, payload));
      } else {
        await firstValueFrom(this.userService.create(payload));
      }

      this.dialogRef.close(true);
    } catch (error) {
      const message = applyServerErrors(this.form, error);
      this.snackBar.open(message, 'Tutup', { duration: 4000 });
    } finally {
      this.saving.set(false);
    }
  }
}
