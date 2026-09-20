export type DropzoneProps = {
    onFileAccepted: (file: File) => void;
    hint?: string;
    accept?: Record<string, string[]>;
};
