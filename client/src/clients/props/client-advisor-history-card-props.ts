export type ClientAdvisorHistoryCardProps = {
    clientId: number;
    currentAdvisorId: number;
    /** Se llama con el cliente ya reasignado para que la ficha se recargue. */
    onReassigned: () => void;
};
