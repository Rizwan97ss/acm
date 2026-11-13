import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { destroy, store, update } from '@/routes/academics/timetable/entries';
import type { Option, SubjectAllocation, TimetableLesson } from '@/types';

export type LessonSlot = {
    dayOfWeek: number;
    dayLabel: string;
    periodId: number;
    periodLabel: string;
    lesson: TimetableLesson | null;
};

type Props = {
    slot: LessonSlot;
    academicYearId: number;
    sectionId: number;
    subjects: SubjectAllocation[];
    teachers: Option[];
    onClose: () => void;
};

/**
 * Add, change or remove the lesson in one timetable slot. Clashes are
 * reported by the server as field errors.
 */
export default function LessonDialog({
    slot,
    academicYearId,
    sectionId,
    subjects,
    teachers,
    onClose,
}: Props) {
    const form = useForm({
        academic_year_id: academicYearId,
        section_id: sectionId,
        day_of_week: slot.dayOfWeek,
        period_id: slot.periodId,
        subject_id: slot.lesson?.subject.id ?? subjects[0]?.id ?? '',
        teacher_id: slot.lesson?.teacher?.id ?? ('' as number | ''),
        room: slot.lesson?.room ?? '',
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: onClose };

        if (slot.lesson) {
            form.put(update.url(slot.lesson.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    const remove = (): void => {
        if (!slot.lesson) {
            return;
        }

        router.visit(destroy(slot.lesson.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>
                    {slot.lesson ? 'Edit lesson' : 'Add lesson'}
                </DialogTitle>
                <DialogDescription>
                    {slot.dayLabel}, {slot.periodLabel}
                </DialogDescription>
                <form onSubmit={submit} className="space-y-4">
                    <FormField
                        id="lesson-subject"
                        label="Subject"
                        error={form.errors.subject_id}
                        required
                    >
                        <NativeSelect
                            id="lesson-subject"
                            value={form.data.subject_id}
                            onChange={(e) =>
                                form.setData(
                                    'subject_id',
                                    Number(e.target.value),
                                )
                            }
                            required
                        >
                            {subjects.map((subject) => (
                                <option key={subject.id} value={subject.id}>
                                    {subject.name} ({subject.scheduled}/
                                    {subject.planned})
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="lesson-teacher"
                        label="Teacher"
                        error={form.errors.teacher_id}
                    >
                        <NativeSelect
                            id="lesson-teacher"
                            value={form.data.teacher_id}
                            onChange={(e) =>
                                form.setData(
                                    'teacher_id',
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                        >
                            <option value="">Not assigned</option>
                            {teachers.map((teacher) => (
                                <option key={teacher.id} value={teacher.id}>
                                    {teacher.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="lesson-room"
                        label="Room"
                        error={form.errors.room}
                        description="Leave empty to use the section's room."
                    >
                        <Input
                            id="lesson-room"
                            value={form.data.room}
                            onChange={(e) =>
                                form.setData('room', e.target.value)
                            }
                            maxLength={30}
                        />
                    </FormField>
                    {form.errors.period_id && (
                        <p className="text-sm text-destructive">
                            {form.errors.period_id}
                        </p>
                    )}
                    <DialogFooter className="gap-2 sm:justify-between">
                        {slot.lesson ? (
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive"
                                onClick={remove}
                            >
                                Remove lesson
                            </Button>
                        ) : (
                            <span />
                        )}
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
