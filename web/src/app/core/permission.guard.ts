import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { MatSnackBar } from '@angular/material/snack-bar';
import { AuthService } from './auth.service';

export const permissionGuard: CanActivateFn = async (route) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const snackBar = inject(MatSnackBar);

  if (!(await auth.ensureLoaded())) {
    return router.createUrlTree(['/login']);
  }

  const requiredPath = route.data['permission'] as string | undefined;

  if (!requiredPath || auth.hasPermission(requiredPath)) {
    return true;
  }

  snackBar.open('Anda tidak memiliki akses ke halaman tersebut.', 'Tutup', { duration: 3500 });

  return router.createUrlTree(['/dashboard']);
};
