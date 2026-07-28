// resources/js/Components/Destinations/DestinationFilterBar.tsx
//
// Top-of-page search-style filter bar for the destinations index. Drives the
// card ranking/layout AND the charts below, and is mirrored to the URL. All
// state lives in the parent; this component is presentational (props in,
// onChange out).

import Select from 'react-select'
import Icon from '@/Components/Common/Icon'
import { faSlidersH } from '@fortawesome/free-solid-svg-icons'
import { MIN_OPTIONS, snapToUnitOption, unitToKts, type WindUnit } from '@/Helpers/sailableDays'
import type { DestinationFilters, GroupBy } from '@/Helpers/destinationFilters'
import type { SelectOption } from '@/Helpers/selectTypes'

// react-select styling reused from the old FilterDataset (brand teal, square corners).
const selectStyles = {
    control: (base: any, state: any) => ({
        ...base,
        backgroundColor: 'white',
        borderColor: state.isFocused ? 'hsl(192 89% 25%)' : 'rgba(0,0,0,0.15)',
        borderRadius: 0,
        boxShadow: 'none',
        color: 'hsl(0 1% 15%)',
        minHeight: '2.75rem',
        '&:hover': { borderColor: 'rgba(0,0,0,0.35)' },
    }),
    singleValue: (base: any) => ({ ...base, color: 'hsl(0 1% 15%)', fontSize: '0.875rem' }),
    multiValue: (base: any) => ({
        ...base,
        backgroundColor: 'hsl(169 28% 89%)',
        borderRadius: 0,
    }),
    multiValueLabel: (base: any) => ({ ...base, color: 'hsl(192 89% 20%)', fontSize: '0.75rem' }),
    multiValueRemove: (base: any) => ({
        ...base,
        color: 'hsl(192 89% 30%)',
        ':hover': { backgroundColor: 'hsl(185 36% 70%)', color: 'hsl(192 89% 15%)' },
    }),
    placeholder: (base: any) => ({ ...base, color: 'rgba(0,0,0,0.4)', fontSize: '0.875rem' }),
    menu: (base: any) => ({
        ...base,
        backgroundColor: 'white',
        borderRadius: 0,
        border: '1px solid rgba(0,0,0,0.1)',
        boxShadow: '0 8px 32px rgba(0,0,0,0.15)',
    }),
    option: (base: any, state: any) => ({
        ...base,
        backgroundColor: state.isSelected
            ? 'hsl(192 89% 25%)'
            : state.isFocused
                ? 'hsl(169 28% 89%)'
                : 'transparent',
        color: state.isSelected ? 'white' : 'hsl(0 1% 15%)',
        fontSize: '0.875rem',
        cursor: 'pointer',
    }),
    input: (base: any) => ({ ...base, color: 'hsl(0 1% 15%)' }),
    dropdownIndicator: (base: any) => ({ ...base, color: 'rgba(0,0,0,0.4)', padding: '0 8px' }),
    clearIndicator: (base: any) => ({ ...base, color: 'rgba(0,0,0,0.4)', padding: '0 8px' }),
    indicatorSeparator: (base: any) => ({ ...base, backgroundColor: 'rgba(0,0,0,0.15)' }),
    valueContainer: (base: any) => ({ ...base, padding: '2px 10px' }),
} as any

interface Props {
    monthOptions: { label: string; value: number }[]
    groupOptions: { label: string; value: GroupBy }[]
    destinationOptions: SelectOption[]
    filters: DestinationFilters
    onChange: (next: DestinationFilters) => void
}

/**
 * Render the destinations filter bar (Month / Group by / Spots / Unit / Minimum).
 */
const DestinationFilterBar = ({ monthOptions, groupOptions, destinationOptions, filters, onChange }: Props) => {
    const unitOptions: { label: string; value: WindUnit }[] = [
        { label: 'kts', value: 'kts' }, { label: 'mph', value: 'mph' }, { label: 'kph', value: 'kph' },
    ]
    const minOptions = MIN_OPTIONS[filters.unit].map((value) => ({ label: `${value} ${filters.unit}`, value }))

    const isAllSpots = filters.spots.length === 0
    const selectedSpotOptions = destinationOptions.filter((opt) => filters.spots.includes(opt.value))

    /** When the unit changes, preserve the wind strength by snapping the current minimum into the new unit. */
    const handleUnitChange = (unit: WindUnit) => {
        const currentKts = unitToKts(filters.min, filters.unit)
        onChange({ ...filters, unit, min: snapToUnitOption(currentKts, unit) })
    }

    return (
        <div className="bg-white border-y border-secondary/10 sticky top-0 z-20">
            <div className="container mx-auto py-4 lg:py-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-5">
                    <div className="flex items-center gap-2.5 shrink-0">
                        <Icon icon={faSlidersH} customClasses="text-primary" size="size-4" />
                        <span className="text-primary text-xs uppercase tracking-[0.2em] font-medium">Find your spot</span>
                    </div>

                    <div className="lg:w-36 shrink-0">
                        <Select
                            options={monthOptions}
                            value={monthOptions.find((opt) => opt.value === filters.month)}
                            onChange={(opt: any) => opt && onChange({ ...filters, month: Number(opt.value) })}
                            styles={selectStyles}
                            isSearchable={false}
                        />
                    </div>

                    <div className="lg:w-40 shrink-0">
                        <Select
                            options={groupOptions}
                            value={groupOptions.find((opt) => opt.value === filters.group)}
                            onChange={(opt: any) => opt && onChange({ ...filters, group: opt.value as GroupBy })}
                            styles={selectStyles}
                            isSearchable={false}
                        />
                    </div>

                    <div className="flex-1 min-w-0">
                        <Select
                            isMulti
                            options={destinationOptions}
                            value={isAllSpots ? null : selectedSpotOptions}
                            placeholder="All destinations"
                            onChange={(opts: any) => onChange({ ...filters, spots: (opts ?? []).map((opt: SelectOption) => opt.value) })}
                            styles={selectStyles}
                        />
                    </div>

                    <div className="lg:w-28 shrink-0">
                        <Select
                            options={unitOptions}
                            value={unitOptions.find((opt) => opt.value === filters.unit)}
                            onChange={(opt: any) => opt && handleUnitChange(opt.value as WindUnit)}
                            styles={selectStyles}
                            isSearchable={false}
                        />
                    </div>

                    <div className="lg:w-36 shrink-0">
                        <Select
                            options={minOptions}
                            value={minOptions.find((opt) => opt.value === filters.min) ?? minOptions[0]}
                            onChange={(opt: any) => opt && onChange({ ...filters, min: Number(opt.value) })}
                            styles={selectStyles}
                            isSearchable={false}
                        />
                    </div>
                </div>
            </div>
        </div>
    )
}

export default DestinationFilterBar
