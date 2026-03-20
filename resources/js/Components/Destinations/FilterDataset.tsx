import Select from 'react-select'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import Icon from '@/Components/Common/Icon'
import { faFilter, faRefresh } from '@fortawesome/free-solid-svg-icons'

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

const customSelectThemeColours = {
    primary25: 'hsl(185, 36%, 90%)',
    primary50: 'hsl(192, 91%, 25%)',
    primary: 'hsl(192, 89%, 15%)',
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
        activeDestinations.every((d) =>
            destinationOptions.some((opt) => opt.value === d.value)
        )

    const handleDestinationChange = (options: readonly SelectOption[]) => {
        if (!options || options.length === 0) {
            setActiveDestinations([...destinationOptions])
            return
        }
        setActiveDestinations([...options])
    }

    const selectTheme = (theme: any) => ({
        ...theme,
        colors: { ...theme.colors, ...customSelectThemeColours },
    })

    return (
        <BlockWrapper options={{ noPadding: true }}>
            <div className="bg-primary-lighter py-4 lg:py-6 px-6 lg:px-8 space-y-4 lg:space-y-0 lg:flex lg:flex-row lg:items-center lg:justify-between lg:gap-x-8">
                <div className="flex items-center max-lg:justify-center gap-x-2">
                    <Icon icon={faFilter} customClasses="text-primary-darker" size="size-4 lg:size-5" />
                    <p className="text-primary-darker lg:text-lg">Filter</p>
                </div>

                <Select
                    options={yearOptions}
                    onChange={(option: any) => option && setActiveYear(Number(option.value))}
                    value={yearOptions.find((opt) => opt.value === activeYear)}
                    theme={selectTheme}
                    className="w-full"
                />

                <Select
                    isMulti
                    options={destinationOptions}
                    onChange={handleDestinationChange as any}
                    value={isAllSelected ? null : activeDestinations}
                    placeholder="All destinations"
                    theme={selectTheme}
                    className="w-full"
                />

                <button
                    className="max-lg:w-full min-w-20 lg:min-w-24 px-2 py-2 flex items-center justify-center gap-x-2 bg-primary hover:bg-primary-darker transition-colors duration-300"
                    onClick={onReset}
                >
                    <p className="text-white lg:text-lg">Reset</p>
                    <Icon icon={faRefresh} customClasses="text-white" size="size-4 lg:size-5" />
                </button>
            </div>
        </BlockWrapper>
    )
}

export default FilterDataset
