import { router } from '@inertiajs/react';
import { Library } from 'lucide-react';
import { useEffect, useState } from 'react';
import SearchInput from '@/components/data/search-input';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { bank } from '@/routes/academics/online-exams';
import { importMethod } from '@/routes/academics/online-exams/questions';

type BankItem = {
    id: number;
    type_label: string;
    difficulty: 'easy' | 'medium' | 'hard';
    difficulty_label: string;
    prompt: string;
    marks: number;
    topic: string | null;
};

const difficultyTone = {
    easy: 'success',
    medium: 'info',
    hard: 'danger',
} as const;

/**
 * Choose questions from the bank (the exam's subject, its class or any class) and copy
 * them onto the paper.
 */
export default function BankPicker({ examId }: { examId: number }) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [difficulty, setDifficulty] = useState('');
    const [items, setItems] = useState<BankItem[] | null>(null);
    const [chosen, setChosen] = useState<number[]>([]);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const query: Record<string, string> = {};

        if (search.trim() !== '') {
            query.search = search.trim();
        }

        if (difficulty !== '') {
            query.difficulty = difficulty;
        }

        const timer = setTimeout(() => {
            fetch(bank.url(examId, { query }), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : []))
                .then((list: BankItem[]) => setItems(list))
                .catch(() => undefined);
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [open, search, difficulty, examId]);

    const chosenMarks = (items ?? [])
        .filter((item) => chosen.includes(item.id))
        .reduce((sum, item) => sum + item.marks, 0);

    const add = (): void => {
        router.post(
            importMethod.url(examId),
            { bank_question_ids: chosen },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: () => {
                    setChosen([]);
                    setOpen(false);
                },
            },
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    setChosen([]);
                    setItems(null);
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Library aria-hidden /> Add from bank
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-hidden sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Add questions from the bank</DialogTitle>
                    <DialogDescription>
                        Questions for this subject and class. Each is copied
                        onto the paper, so editing the bank later never changes
                        this exam.
                    </DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <SearchInput
                        value={search}
                        onChange={setSearch}
                        placeholder="Question text or topic"
                    />
                    <NativeSelect
                        aria-label="Difficulty"
                        value={difficulty}
                        onChange={(event) => setDifficulty(event.target.value)}
                        className="sm:w-40"
                    >
                        <option value="">Any difficulty</option>
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                    </NativeSelect>
                </div>
                <div className="-mx-1 max-h-[45vh] overflow-y-auto px-1">
                    {items === null ? (
                        <p className="flex items-center gap-2 p-4 text-sm text-muted-foreground">
                            <Spinner /> Loading…
                        </p>
                    ) : items.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                            No bank questions match. Add some in the question
                            bank, or save questions from your exams to it.
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {items.map((item) => (
                                <li key={item.id}>
                                    <label className="flex cursor-pointer items-start gap-3 rounded-xl border p-3 hover:bg-muted/40 has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5">
                                        <Checkbox
                                            className="mt-0.5"
                                            checked={chosen.includes(item.id)}
                                            onCheckedChange={(checked) =>
                                                setChosen((current) =>
                                                    checked === true
                                                        ? [...current, item.id]
                                                        : current.filter(
                                                              (id) =>
                                                                  id !==
                                                                  item.id,
                                                          ),
                                                )
                                            }
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm break-words">
                                                {item.prompt}
                                            </span>
                                            <span className="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                                {item.type_label} · {item.marks}{' '}
                                                {item.marks === 1
                                                    ? 'mark'
                                                    : 'marks'}
                                                {item.topic &&
                                                    ` · ${item.topic}`}
                                                <TonePill
                                                    tone={
                                                        difficultyTone[
                                                            item.difficulty
                                                        ]
                                                    }
                                                >
                                                    {item.difficulty_label}
                                                </TonePill>
                                            </span>
                                        </span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                <DialogFooter className="items-center gap-2 sm:justify-between">
                    <p className="text-sm text-muted-foreground">
                        {chosen.length} chosen · {chosenMarks} marks
                    </p>
                    <Button
                        onClick={add}
                        disabled={chosen.length === 0 || saving}
                    >
                        {saving && <Spinner />} Add to paper
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
