import React from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

export default function Modal({
    isOpen,
    onClose,
    title,
    description,
    children,
    maxWidth = 'sm:max-w-xl',
}) {
    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className={cn("rounded-lg bg-card text-card-foreground border-border shadow-2xl p-6", maxWidth)}>
                {(title || description) && (
                    <DialogHeader className="space-y-1 pb-3 border-b border-border/60">
                        {title && (
                            <DialogTitle className="text-base sm:text-lg font-bold text-foreground">
                                {title}
                            </DialogTitle>
                        )}
                        {description && (
                            <DialogDescription className="text-xs text-muted-foreground">
                                {description}
                            </DialogDescription>
                        )}
                    </DialogHeader>
                )}
                <div className="pt-2">
                    {children}
                </div>
            </DialogContent>
        </Dialog>
    );
}
