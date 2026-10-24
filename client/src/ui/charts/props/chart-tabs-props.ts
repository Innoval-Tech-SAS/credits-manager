export type ChartTabOption = {
    value: string;
    label: string;
};

export type ChartTabsProps = {
    options: ChartTabOption[];
    value: string;
    onChange: (value: string) => void;
};
