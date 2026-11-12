import { Routes } from '@angular/router';
import { authGuard } from './core/auth.guard';
import { permissionGuard } from './core/permission.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./pages/login/login.component').then((m) => m.LoginComponent),
  },
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () => import('./layout/layout.component').then((m) => m.LayoutComponent),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./pages/dashboard/dashboard.component').then((m) => m.DashboardComponent),
      },
      {
        path: 'users',
        canActivate: [permissionGuard],
        data: { permission: '/users' },
        loadComponent: () =>
          import('./pages/users/user-list.component').then((m) => m.UserListComponent),
      },
      {
        path: 'levels',
        canActivate: [permissionGuard],
        data: { permission: '/levels' },
        loadComponent: () =>
          import('./pages/levels/level-list.component').then((m) => m.LevelListComponent),
      },
      {
        path: 'pages',
        canActivate: [permissionGuard],
        data: { permission: '/pages' },
        loadComponent: () =>
          import('./pages/pages/page-list.component').then((m) => m.PageListComponent),
      },
      {
        path: 'permissions',
        canActivate: [permissionGuard],
        data: { permission: '/permissions' },
        loadComponent: () =>
          import('./pages/permissions/permissions.component').then((m) => m.PermissionsComponent),
      },
    ],
  },
  { path: '**', redirectTo: 'dashboard' },
];
