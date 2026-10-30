import React from 'react';
import { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

interface PageHeaderProps {
  title: string;
  description?: string;
  icon?: LucideIcon;
  badge?: string;
  badgeVariant?: 'default' | 'secondary' | 'outline' | 'destructive';
  actions?: React.ReactNode;
}

export function PageHeader({
  title,
  description,
  icon: Icon,
  badge,
  badgeVariant = 'secondary',
  actions,
}: PageHeaderProps) {
  return (
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1">
      <div>
        <div className="flex items-center gap-2.5">
          {Icon && (
            <div className="h-9 w-9 rounded-md bg-[#004c68]/10 text-[#004c68] dark:bg-[#004c68]/20 dark:text-[#ff8806] flex items-center justify-center shrink-0">
              <Icon className="h-5 w-5" />
            </div>
          )}
          <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
            {title}
            {badge && (
              <Badge variant={badgeVariant} className="rounded-md font-semibold text-xs">
                {badge}
              </Badge>
            )}
          </h1>
        </div>
        {description && (
          <p className="text-sm text-muted-foreground mt-1">
            {description}
          </p>
        )}
      </div>

      {actions && (
        <div className="flex items-center gap-2.5 shrink-0">
          {actions}
        </div>
      )}
    </div>
  );
}

