import { useState } from 'react'
import { faSailboat, faCalendar, faCompass, faWind, faRocket, faChevronLeft } from '@fortawesome/free-solid-svg-icons'
import Icon from './Icon'

interface SpotOverviewData {
    sailing_style?: string
    best_conditions?: string
    best_direction?: string
    wind_conditions?: string
    water_conditions?: string
    launch_zone?: string
}

interface SpotOverviewProps {
    spotOverview: SpotOverviewData
}

const SpotOverview = ({ spotOverview }: SpotOverviewProps) => {
    const [overviewIsOpen, setOverviewIsOpen] = useState(false)

    const spotOverviewData = [
        { title: 'Sailing Style', icon: faSailboat, value: spotOverview.sailing_style },
        { title: 'Best Time', icon: faCalendar, value: spotOverview.best_conditions },
        { title: 'Best Direction', icon: faCompass, value: spotOverview.best_direction },
        { title: 'Wind Conditions', icon: faWind, value: spotOverview.wind_conditions },
        { title: 'Launch Zone', icon: faRocket, value: spotOverview.launch_zone },
    ].filter(item => item.value)

    if (spotOverviewData.length === 0) return null

    const wrapperClasses = [
        'max-lg:container max-lg:mx-auto max-lg:flex max-lg:items-center max-lg:justify-center',
        'md:min-h-[20vh]',
        'lg:absolute lg:right-0 lg:top-0 lg:h-full lg:bg-secondary/90 lg:transition-all lg:duration-300',
        overviewIsOpen ? 'lg:w-[20vw]' : 'lg:w-[5rem]'
    ].join(' ')

    const listClasses = [
        'relative w-full h-full grid grid-cols-3 py-4 gap-4',
        'md:grid-cols-5',
        'lg:gap-x-0 lg:flex lg:flex-col lg:justify-evenly lg:gap-y-20'
    ].join(' ')

    const buttonClasses = [
        'absolute top-1/2 left-0 z-10 p-2 -translate-y-1/2 -translate-x-full',
        'bg-secondary/90 rounded-l-md flex items-center justify-center',
    ].join(' ')

    const textWrapperClasses = [
        'flex flex-col items-center lg:items-start',
        overviewIsOpen ? 'lg:block' : 'lg:hidden'
    ].join(' ')

    return (
        <div className={wrapperClasses}>
            <ul className={listClasses}>
                {spotOverviewData.map(({ title, icon, value }, index) => {
                    const itemClasses = [
                        'flex flex-col gap-4 items-center lg:flex-row lg:gap-6 lg:px-4',
                        overviewIsOpen ? 'lg:justify-start' : 'lg:justify-center'
                    ].join(' ')

                    return (
                        <li key={index} className={itemClasses}>
                            <div className="size-8 lg:size-10 rounded-full bg-primary-lighter flex items-center justify-center">
                                <Icon icon={icon} customClasses="text-white" size="size-4 lg:size-5" />
                            </div>
                            <div className={textWrapperClasses}>
                                <h3 className="hidden lg:block text-primary-lighter text-center lg:text-left text-sm">
                                    {title}
                                </h3>
                                <p className="text-white text-center lg:text-left text-xs lg:text-sm">
                                    {value}
                                </p>
                            </div>
                        </li>
                    )
                })}
            </ul>

            <button
                className={buttonClasses}
                onClick={() => setOverviewIsOpen(!overviewIsOpen)}
            >
                <Icon
                    icon={faChevronLeft}
                    customClasses={`text-primary-lighter transition duration-300 ${overviewIsOpen ? 'rotate-180' : 'rotate-0'}`}
                    size="size-4"
                />
            </button>
        </div>
    )
}

export default SpotOverview
