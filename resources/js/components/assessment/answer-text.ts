import type { QuestionType, QuizOption } from '@/types/learning';

/**
 * A stored answer as readable text: option texts for choice questions, True/False, or
 * the written answer.
 */
export function answerText(
    type: QuestionType,
    options: QuizOption[],
    value: string | string[] | null | undefined,
    empty = 'No answer',
): string {
    if (value === null || value === undefined || value === '') {
        return empty;
    }

    if (type === 'true_false') {
        return value === 'true' ? 'True' : 'False';
    }

    if (options.length > 0) {
        const ids = Array.isArray(value) ? value : [value];

        return (
            options
                .filter((option) => ids.includes(option.id))
                .map((option) => option.text)
                .join(', ') || empty
        );
    }

    return Array.isArray(value) ? value.join(', ') : value;
}

/**
 * The correct answer as readable text, or null when the teacher marks it by hand.
 */
export function keyText(
    type: QuestionType,
    options: QuizOption[],
    key: string[],
): string | null {
    if (key.length === 0) {
        return null;
    }

    if (type === 'short_answer') {
        return key.join(' / ');
    }

    return answerText(type, options, type === 'multiple_choice' ? key : key[0]);
}
