import { router } from '@inertiajs/react';
import { LogOut, TimerReset } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { IDLE_WARNING_SECONDS, useIdleLogout } from '@/hooks/use-idle-logout';
import { logout } from '@/routes';

/**
 * Warns a minute before the idle timeout signs the user out, with a countdown.
 * Any activity, or "Stay signed in", keeps the session alive.
 */
export default function IdleLogout() {
    const { secondsLeft, staySignedIn } = useIdleLogout();
    const open = secondsLeft !== null;
    const seconds = secondsLeft ?? IDLE_WARNING_SECONDS;
    const progress = seconds / IDLE_WARNING_SECONDS;
    const circumference = 2 * Math.PI * 26;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    staySignedIn();
                }
            }}
        >
            <DialogContent
                role="alertdialog"
                className="sm:max-w-md"
                onPointerDownOutside={(event) => event.preventDefault()}
            >
                <div className="flex items-start gap-4">
                    <div className="relative size-16 shrink-0" aria-hidden>
                        <svg viewBox="0 0 60 60" className="size-16 -rotate-90">
                            <circle
                                cx="30"
                                cy="30"
                                r="26"
                                className="fill-none stroke-muted"
                                strokeWidth="5"
                            />
                            <circle
                                cx="30"
                                cy="30"
                                r="26"
                                className="fill-none stroke-gold-500 transition-[stroke-dashoffset] duration-1000 ease-linear"
                                strokeWidth="5"
                                strokeLinecap="round"
                                strokeDasharray={circumference}
                                strokeDashoffset={
                                    circumference * (1 - progress)
                                }
                            />
                        </svg>
                        <span className="absolute inset-0 flex items-center justify-center text-lg font-bold tabular-nums">
                            {seconds}
                        </span>
                    </div>
                    <div className="space-y-1.5">
                        <DialogTitle>Are you still there?</DialogTitle>
                        <DialogDescription>
                            For your security you’ll be signed out in{' '}
                            <strong className="text-foreground tabular-nums">
                                {seconds} {seconds === 1 ? 'second' : 'seconds'}
                            </strong>{' '}
                            because there has been no activity.
                        </DialogDescription>
                        <p className="sr-only" aria-live="assertive">
                            {seconds % 15 === 0 || seconds <= 10
                                ? `${seconds} seconds until sign-out`
                                : ''}
                        </p>
                    </div>
                </div>
                <DialogFooter className="gap-2">
                    <Button
                        variant="outline"
                        onClick={() => router.post(logout())}
                    >
                        <LogOut aria-hidden />
                        Sign out now
                    </Button>
                    <Button onClick={staySignedIn} autoFocus>
                        <TimerReset aria-hidden />
                        Stay signed in
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
