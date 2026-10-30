import React from 'react';
import { LucideIcon, TrendingUp, TrendingDown, Minus } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

interface StatsCardProps {
  title: string;
  value: string | number | undefined | null;
  description?: string;
  trend?: {
    value: string | number;
    isPositive?: boolean;
    period?: string; // ex: "vs mois précédent"
  };
  icon?: LucideIcon;
  iconColor?: string;
  className?: string;
}

export function StatsCard({
  title,
  value,
  description,
  trend,
  icon: Icon,
  iconColor = 'text-[#004c68] dark:text-[#ff8806]',
  className,
}: StatsCardProps) {
  const displayValue = value !== undefined && value !== null ? value : '-';

  return (
    <Card className={cn("rounded-md border border-border bg-card shadow-xs transition-shadow hover:shadow-sm", className)}>
      <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
        <CardTitle className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
          {title}
        </CardTitle>
        {Icon && (
          <div className="h-8 w-8 rounded-md bg-muted/60 flex items-center justify-center shrink-0">
            <Icon className={cn("h-4 w-4", iconColor)} />
          </div>
        )}
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
          {displayValue}
        </div>

        {trend && (
          <div className="flex items-center gap-1.5 mt-1 text-xs">
            {trend.isPositive === true && (
              <span className="inline-flex items-center text-emerald-600 dark:text-emerald-400 font-medium">
                <TrendingUp className="mr-0.5 h-3 w-3" />
                {trend.value}
              </span>
            )}
            {trend.isPositive === false && (
              <span className="inline-flex items-center text-rose-600 dark:text-rose-400 font-medium">
                <TrendingDown className="mr-0.5 h-3 w-3" />
                {trend.value}
              </span>
            )}
            {trend.isPositive === undefined && (
              <span className="inline-flex items-center text-muted-foreground font-medium">
                <Minus className="mr-0.5 h-3 w-3" />
                {trend.value}
              </span>
            )}
            {trend.period && (
              <span className="text-muted-foreground">
                {trend.period}
              </span>
            )}
          </div>
        )}

        {description && !trend && (
          <p className="text-xs text-muted-foreground mt-1">
            {description}
          </p>
        )}
      </CardContent>
    </Card>
  );
}
