import { HttpErrorResponse } from '@angular/common/http';
import { FormGroup } from '@angular/forms';
import { ApiErrorBody } from './models';

export function getErrorMessage(error: unknown, fallback = 'Terjadi kesalahan. Silakan coba lagi.'): string {
  if (error instanceof HttpErrorResponse) {
    const body = error.error as Partial<ApiErrorBody> | null;

    if (body && typeof body.message === 'string' && body.message !== '') {
      return body.message;
    }
  }

  return fallback;
}

export function clearServerErrors(form: FormGroup): void {
  Object.values(form.controls).forEach((control) => {
    const errors = control.errors;

    if (errors && Object.keys(errors).length === 1 && errors['server']) {
      control.setErrors(null);
    }
  });
}

export function applyServerErrors(form: FormGroup, error: unknown): string {
  if (error instanceof HttpErrorResponse) {
    const body = error.error as Partial<ApiErrorBody> | null;
    let hasFieldErrors = false;

    if (body?.errors) {
      Object.entries(body.errors).forEach(([field, messages]) => {
        const control = form.get(field);

        if (control) {
          control.setErrors({ server: messages.join(' ') });
          control.markAsTouched();
          hasFieldErrors = true;
        }
      });
    }

    if (body?.message) {
      return body.message;
    }

    if (hasFieldErrors) {
      return 'Periksa kembali isian form.';
    }
  }

  return 'Terjadi kesalahan. Silakan coba lagi.';
}
