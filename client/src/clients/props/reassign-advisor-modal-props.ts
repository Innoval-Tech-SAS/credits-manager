export type ReassignAdvisorModalProps = {
    isOpen: boolean;
    onClose: () => void;
    clientId: number;
    currentAdvisorId: number;
    onReassigned: () => void;
};
