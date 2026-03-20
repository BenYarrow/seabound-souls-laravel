import { useMemo, useState } from 'react'
import Select from 'react-select'
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import Icon from '@/Components/Common/Icon'
import { faFilter, faRefresh } from '@fortawesome/free-solid-svg-icons'

interface WeatherMonth {
    month: string
    avg_temp: number
    kts_wind: number
    kts_gust: number
    mph_wind: number
    mph_gust: number
    kph_wind: number
    kph_gust: number
}

interface SelectOption<T = string | number> {
    label: T
    value: T
}

interface Props {
    weatherRecords: Record<string, WeatherMonth[]>
}

const customSelectThemeColours = {
    primary25: 'hsl(185, 36%, 90%)',
    primary50: 'hsl(192, 91%, 25%)',
    primary: 'hsl(192, 89%, 15%)',
}

const unitOptions: SelectOption<string>[] = [
    { label: 'Knots', value: 'kts' },
    { label: 'Mph', value: 'mph' },
    { label: 'Kph', value: 'kph' },
]

const SpotGuideStatistics = ({ weatherRecords }: Props) => {
    const years = Object.keys(weatherRecords).map(Number).sort((a, b) => b - a)
    const currentYear = new Date().getFullYear()

    const [activeYear, setActiveYear] = useState<number>(
        years.includes(currentYear) ? currentYear : years[0]
    )
    const [activeWindUnit, setActiveWindUnit] = useState<string>('kts')

    const yearOptions: SelectOption<number>[] = years.map((y) => ({ label: y, value: y }))

    const chartData = useMemo(() => {
        const raw = weatherRecords[activeYear] ?? []
        return raw.map((r) => ({
            month: r.month,
            avgTemp: Number(r.avg_temp),
            ktsWind: Number(r.kts_wind),
            ktsGust: Number(r.kts_gust),
            mphWind: Number(r.mph_wind),
            mphGust: Number(r.mph_gust),
            kphWind: Number(r.kph_wind),
            kphGust: Number(r.kph_gust),
        }))
    }, [weatherRecords, activeYear])

    if (!chartData.length) return null

    const formattedUnitLabel = activeWindUnit.charAt(0).toUpperCase() + activeWindUnit.slice(1)

    const handleReset = () => {
        setActiveYear(years.includes(currentYear) ? currentYear : years[0])
        setActiveWindUnit('kts')
    }

    const CustomTooltip = ({ payload, dataKey }: any) => {
        if (!payload?.length) return null
        const data = payload[0].payload
        const { month } = data

        return (
            <div className="min-w-[10rem] bg-white p-2">
                <p className="text-primary text-lg border-b border-primary flex items-center justify-between gap-x-1 lg:gap-x-2">
                    {month}
                </p>
                {dataKey === 'temp' ? (
                    <p className="flex items-center justify-between gap-x-4 text-[#990000]">
                        Avg Temp: <span>{`${data.avgTemp} °C`}</span>
                    </p>
                ) : (
                    <ul className="w-full py-1 lg:py-2 space-y-1 lg:space-y-2">
                        <li className="flex items-center justify-between gap-x-4 text-primary-darker">
                            Gusts: <span>{`${data[`${activeWindUnit}Gust`]} ${activeWindUnit}`}</span>
                        </li>
                        <li className="flex items-center justify-between gap-x-4 text-primary">
                            Wind: <span>{`${data[`${activeWindUnit}Wind`]} ${activeWindUnit}`}</span>
                        </li>
                    </ul>
                )}
            </div>
        )
    }

    const selectTheme = (theme: any) => ({
        ...theme,
        colors: { ...theme.colors, ...customSelectThemeColours },
    })

    return (
        <BlockWrapper>
            <div className="space-y-8 lg:space-y-12">
                {/* Filters */}
                <div className="bg-primary-lighter py-4 lg:py-6 px-6 lg:px-8 space-y-4 lg:space-y-0 lg:flex lg:flex-row lg:items-center lg:justify-between lg:gap-x-8">
                    <div className="flex items-center max-lg:justify-center gap-x-2">
                        <Icon icon={faFilter} customClasses="text-primary-darker" size="size-4 lg:size-5" />
                        <p className="text-primary-darker lg:text-lg">Filter</p>
                    </div>

                    <Select
                        options={yearOptions}
                        onChange={(option: SelectOption<number> | null) => option && setActiveYear(option.value)}
                        value={yearOptions.find((opt) => opt.value === activeYear)}
                        theme={selectTheme}
                        className="min-w-[200px]"
                    />

                    <Select
                        options={unitOptions}
                        onChange={(option: SelectOption<string> | null) => option && setActiveWindUnit(option.value)}
                        value={unitOptions.find(({ value }) => value === activeWindUnit)}
                        theme={selectTheme}
                        className="min-w-[200px]"
                    />

                    <button
                        className="max-lg:w-full min-w-20 lg:min-w-24 px-2 py-2 flex items-center justify-center gap-x-2 bg-primary hover:bg-primary-darker transition-colors duration-300"
                        onClick={handleReset}
                    >
                        <p className="text-white lg:text-lg">Reset</p>
                        <Icon icon={faRefresh} customClasses="text-white" size="size-4 lg:size-5" />
                    </button>
                </div>

                {/* Wind Chart */}
                <div className="relative">
                    <div className="absolute min-w-20 lg:min-w-24 flex items-center justify-center z-20 top-0 -translate-y-1/2 right-6 lg:right-8 bg-primary shadow-lg">
                        <h3 className="text-white text-lg px-3 py-2">{activeYear}</h3>
                    </div>
                    <div className="w-full p-6 lg:p-8 bg-white space-y-8 shadow-lg">
                        <div className="space-y-2 max-lg:pt-2">
                            <h2 className="text-lg lg:text-xl text-primary">Average wind statistics</h2>
                        </div>
                        <div className="h-[25rem]">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={chartData} margin={{ top: 0, right: 5, left: 0, bottom: 50 }}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="month" interval={0} angle={-45} textAnchor="end" />
                                    <YAxis label={{ value: formattedUnitLabel, angle: -90, position: 'insideLeft' }} />
                                    <Tooltip content={<CustomTooltip dataKey="wind" />} />
                                    <Bar dataKey={`${activeWindUnit}Gust`} name="Gusts" fill="hsl(192, 89%, 15%)" />
                                    <Bar dataKey={`${activeWindUnit}Wind`} name="Wind" fill="hsl(192, 89%, 25%)" />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                        <div className="prose prose-sm prose-p:!text-gray-600 prose-p:text-sm">
                            <p>
                                <strong>Please note:</strong> Wind data is calculated from 4 years of historical records obtained from nearby official weather stations via the{' '}
                                <a href="https://open-meteo.com/" target="_blank" rel="noreferrer noopener">Open-Meteo API</a>.
                                These values are long-term averages and do not account for localised thermal effects (such as sea/land breezes or valley winds).
                                Actual, real-time conditions, particularly in warmer seasons, may vary significantly.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Temperature Chart */}
                <div className="relative">
                    <div className="absolute min-w-20 lg:min-w-24 flex items-center justify-center z-20 top-0 -translate-y-1/2 right-6 lg:right-8 bg-primary shadow-lg">
                        <h3 className="text-white text-lg px-3 py-2">{activeYear}</h3>
                    </div>
                    <div className="w-full p-6 lg:p-8 bg-white space-y-8 shadow-lg">
                        <div className="space-y-2 max-lg:pt-2">
                            <h2 className="text-lg lg:text-xl text-primary">Average temperature statistics</h2>
                        </div>
                        <div className="h-[25rem]">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={chartData} margin={{ top: 0, right: 5, left: 0, bottom: 50 }}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="month" interval={0} angle={-45} textAnchor="end" />
                                    <YAxis label={{ value: 'Temperature', angle: -90, position: 'insideLeft' }} />
                                    <Tooltip content={<CustomTooltip dataKey="temp" />} />
                                    <Bar dataKey="avgTemp" fill="#990000" />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>
                </div>
            </div>
        </BlockWrapper>
    )
}

export default SpotGuideStatistics
