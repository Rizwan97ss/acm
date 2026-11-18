import { useEffect, useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { roster } from '@/routes/learning';

type Student = { id: number; name: string; section_id: number };

/**
 * "Only selected students": a checkbox that reveals the class or section roster to pick from.
 */
export default function StudentChooser({
    classId,
    sectionId,
    value,
    onChange,
    error,
}: {
    classId: number | '';
    sectionId: number | '';
    value: number[];
    onChange: (ids: number[]) => void;
    error?: string;
}) {
    const [restrict, setRestrict] = useState(value.length > 0);
    const [students, setStudents] = useState<Student[] | null>(null);

    useEffect(() => {
        if (!restrict || classId === '') {
            return;
        }

        const controller = new AbortController();
        const query: Record<string, string> = {
            school_class_id: String(classId),
        };

        if (sectionId !== '') {
            query.section_id = String(sectionId);
        }

        setStudents(null);
        fetch(roster.url({ query }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : []))
            .then((list: Student[]) => setStudents(list))
            .catch(() => {});

        return () => controller.abort();
    }, [restrict, classId, sectionId]);

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <Checkbox
                    id="restrict-students"
                    checked={restrict}
                    disabled={classId === ''}
                    onCheckedChange={(checked) => {
                        setRestrict(checked === true);

                        if (checked !== true) {
                            onChange([]);
                        }
                    }}
                />
                <Label htmlFor="restrict-students">
                    Only selected students
                </Label>
            </div>
            {restrict && (
                <div className="max-h-60 overflow-y-auto rounded-xl border p-3">
                    {students === null ? (
                        <p className="text-sm text-muted-foreground">
                            Loading students…
                        </p>
                    ) : students.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No students are enrolled here yet.
                        </p>
                    ) : (
                        <ul className="grid gap-2 sm:grid-cols-2">
                            {students.map((student) => (
                                <li
                                    key={student.id}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`student-${student.id}`}
                                        checked={value.includes(student.id)}
                                        onCheckedChange={() =>
                                            onChange(
                                                value.includes(student.id)
                                                    ? value.filter(
                                                          (id) =>
                                                              id !== student.id,
                                                      )
                                                    : [...value, student.id],
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={`student-${student.id}`}
                                        className="font-normal"
                                    >
                                        {student.name}
                                    </Label>
                                </li>
                            ))}
                        </ul>
                    )}
                    {error && (
                        <p className="mt-2 text-sm text-destructive">{error}</p>
                    )}
                </div>
            )}
        </div>
    );
}
