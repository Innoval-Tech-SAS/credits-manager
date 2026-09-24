export type StateSelectorProps = {
    /** Id del estado elegido, o cadena vacía si el usuario cambió de país y aún no elige uno. */
    onChange: (stateId: string) => void;
    initialCountryId?: string;
    initialStateId?: string;
};
