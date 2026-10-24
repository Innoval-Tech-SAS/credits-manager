export type DropzoneProps = {
    onFileAccepted: (file: File) => void;
    id?: string;
    name?: string;
    label?: string;
    hint?: string;
    accept?: Record<string, string[]>;
};
