import Select from 'react-select'
import Icon from '@/Components/Common/Icon'
import { faSlidersH, faRotateLeft } from '@fortawesome/free-solid-svg-icons'

export interface SelectOption {
    label: string
    value: string
}

interface Props {
    yearOptions: { label: number; value: number }[]
    destinationOptions: SelectOption[]
    activeYear: number
    setActiveYear: (year: number) => void
    activeDestinations: SelectOption[]
    setActiveDestinations: (destinations: SelectOption[]) => void
    onReset: () => void
}

const selectStyles = {
    control: (base: any, state: any) => ({
        ...base,
        backgroundColor: 'rgba(255,255,255,0.07)',
        borderColor: state.isFocused ? 'hsl(185 36% 70%)' : 'rgba(255,255,255,0.15)',
        borderRadius: 0,
        boxShadow: 'none',
        color: 'white',
        minHeight: '2.75rem',
        '&:hover': { borderColor: 'rgba(255,255,255,0.35)' },
    }),
    singleValue: (base: any) => ({ ...base, color: 'white', fontSize: '0.875rem' }),
    multiValue: (base: any) => ({
        ...base,
        backgroundColor: 'rgba(255,255,255,0.15)',
        borderRadius: 0,
    }),
    multiValueLabel: (base: any) => ({ ...base, color: 'white', fontSize: '0.75rem' }),
    multiValueRemove: (base: any) => ({
        ...base,
        color: 'rgba(255,255,255,0.6)',
        ':hover': { backgroundColor: 'rgba(255,255,255,0.25)', color: 'white' },
    }),
    placeholder: (base: any) => ({ ...base, color: 'rgba(255,255,255,0.4)', fontSize: '0.875rem' }),
    menu: (base: any) => ({
        ...base,
        backgroundColor: 'hsl(192 89% 14%)',
        borderRadius: 0,
        border: '1px solid rgba(255,255,255,0.1)',
        boxShadow: '0 8px 32px rgba(0,0,0,0.5)',
    }),
    option: (base: any, state: any) => ({
        ...base,
        backgroundColor: state.isSelected
            ? 'hsl(192 89% 25%)'
            : state.isFocused
                ? 'rgba(255,255,255,0.08)'
                : 'transparent',
        color: state.isSelected ? 'white' : 'rgba(255,255,255,0.8)',
        fontSize: '0.875rem',
        cursor: 'pointer',
    }),
    input: (base: any) => ({ ...base, color: 'white' }),
    dropdownIndicator: (base: any) => ({ ...base, color: 'rgba(255,255,255,0.4)', padding: '0 8px' }),
    clearIndicator: (base: any) => ({ ...base, color: 'rgba(255,255,255,0.4)', padding: '0 8px' }),
    indicatorSeparator: (base: any) => ({ ...base, backgroundColor: 'rgba(255,255,255,0.15)' }),
    valueContainer: (base: any) => ({ ...base, padding: '2px 10px' }),
}

const FilterDataset = ({
    yearOptions,
    destinationOptions,
    activeYear,
    setActiveYear,
    activeDestinations,
    setActiveDestinations,
    onReset,
}: Props) => {
    const isAllSelected =
        activeDestinations.length === destinationOptions.length &&
        activeDestinations.every((d) => destinationOptions.some((opt) => opt.value === d.value))

    const handleDestinationChange = (options: readonly SelectOption[]) => {
        if (!options || options.length === 0) {
            setActiveDestinations([...destinationOptions])
            return
        }
        setActiveDestinations([...options])
    }

    return (
        <div className="bg-primary border-y border-white/10">
            <div className="container mx-auto py-4 lg:py-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-6">

                    {/* Label */}
                    <div className="flex items-center gap-2.5 shrink-0">
                        <Icon icon={faSlidersH} customClasses="text-primary-lighter" size="size-4" />
                        <span className="text-primary-lighter text-xs uppercase tracking-[0.2em] font-medium">
                            Filter data
                        </span>
                    </div>

                    {/* Divider */}
                    <div className="hidden lg:block w-px h-6 bg-white/15 shrink-0" />

                    {/* Year select */}
                    <div className="lg:w-40 shrink-0">
                        <Select
                            options={yearOptions}
                            onChange={(option: any) => option && setActiveYear(Number(option.value))}
                            value={yearOptions.find((opt) => opt.value === activeYear)}
                            styles={selectStyles}
                            isSearchable={false}
                        />
                    </div>

                    {/* Destinations select */}
                    <div className="flex-1 min-w-0">
                        <Select
                            isMulti
                            options={destinationOptions}
                            onChange={handleDestinationChange as any}
                            value={isAllSelected ? null : activeDestinations}
                            placeholder="All destinations"
                            styles={selectStyles}
                        />
                    </div>

                    {/* Reset */}
                    <button
                        className="shrink-0 flex items-center justify-center gap-2 px-4 py-2.5 border border-white/20 text-white/70 hover:text-white hover:border-white/50 text-xs uppercase tracking-wide transition-all duration-200"
                        onClick={onReset}
                    >
                        <Icon icon={faRotateLeft} size="size-3.5" />
                        Reset
                    </button>
                </div>
            </div>
        </div>
    )
}

export default FilterDataset
