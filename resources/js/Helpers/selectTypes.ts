// resources/js/Helpers/selectTypes.ts
//
// Shared react-select option shape. Lived in FilterDataset.tsx originally; moved
// here so components can import it without depending on that (soon-deleted) file.

export interface SelectOption {
    label: string
    value: string
}
