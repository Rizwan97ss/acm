import { Search } from 'lucide-react';
import { Input } from '@/components/ui/input';

type SearchInputProps = {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    label?: string;
};

export default function SearchInput({
    value,
    onChange,
    placeholder = 'Search…',
    label = 'Search',
}: SearchInputProps) {
    return (
        <div className="relative w-full sm:max-w-xs">
            <Search
                className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden
            />
            <Input
                type="search"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                aria-label={label}
                className="pl-8"
            />
        </div>
    );
}
