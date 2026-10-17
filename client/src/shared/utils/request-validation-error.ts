/** 422 de Laravel: el mensaje sigue siendo el primer error (como antes) y `fieldErrors` deja marcar cada campo. */
export class RequestValidationError extends Error {
    readonly fieldErrors: Record<string, string[]>;

    constructor(message: string, fieldErrors: Record<string, string[]>) {
        super(message);
        this.fieldErrors = fieldErrors;
    }
}
