// resources/js/Components/Destinations/SailableDaysChart.tsx
//
// "Sailable days per month" comparison chart: a grouped bar chart with one bar
// per selected spot per month, y-axis = typical sailable days, with the
// selected month marked by a reference line. Reacts to the minimum/unit/spot
// filters via the ranked data it is given.

import {
    BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, ReferenceLine, Legend,
} from 'recharts'
import { prepareSailableChartData, MONTH_LABELS } from '@/Helpers/sailableChartData'
import type { RankedSpot } from '@/Helpers/sailableDays'

interface Props {
    ranked: RankedSpot[]
    colours: Record<string, string>
    /** 1-12; the month currently driving the ranking, highlighted on the chart. */
    selectedMonth: number
    /** e.g. "20 kts" — shown in the axis label so the chart reads on its own. */
    minLabel: string
}

/**
 * Render the sailable-days-per-month grouped bar chart for the ranked spots.
 */
const SailableDaysChart = ({ ranked, colours, selectedMonth, minLabel }: Props) => {
    const data = prepareSailableChartData(ranked)

    return (
        <div className="bg-white p-5 lg:p-6 border border-secondary/10">
            <h3 className="text-secondary font-medium mb-1">Sailable days per month</h3>
            <p className="text-secondary/50 text-sm mb-5">
                Typical days with 2+ hours at or above {minLabel}
            </p>
            <ResponsiveContainer width="100%" height={360}>
                <BarChart data={data} margin={{ top: 8, right: 16, bottom: 4, left: -8 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="rgba(0,0,0,0.08)" />
                    <XAxis dataKey="month" tick={{ fontSize: 12 }} />
                    <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
                    <Tooltip />
                    <Legend />
                    <ReferenceLine
                        x={MONTH_LABELS[selectedMonth - 1]}
                        stroke="hsl(11 61% 58%)"
                        strokeDasharray="4 2"
                    />
                    {ranked.map((spot) => (
                        <Bar
                            key={spot.title}
                            dataKey={spot.title}
                            fill={colours[spot.title]}
                            radius={[2, 2, 0, 0]}
                            maxBarSize={40}
                        />
                    ))}
                </BarChart>
            </ResponsiveContainer>
        </div>
    )
}

export default SailableDaysChart
