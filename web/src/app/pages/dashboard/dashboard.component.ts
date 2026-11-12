import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-dashboard',
  imports: [RouterLink, MatCardModule, MatIconModule],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.css',
})
export class DashboardComponent {
  readonly auth = inject(AuthService);

  iconFor(path: string): string {
    const icons: Record<string, string> = {
      '/dashboard': 'dashboard',
      '/users': 'group',
      '/levels': 'military_tech',
      '/pages': 'description',
      '/permissions': 'admin_panel_settings',
    };

    return icons[path] ?? 'chevron_right';
  }
}
