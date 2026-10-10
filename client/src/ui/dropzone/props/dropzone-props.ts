export type DropzoneProps = {
    onFileAccepted: (file: File) => void;
    label?: string;
    hint?: string;
    accept?: Record<string, string[]>;
};
