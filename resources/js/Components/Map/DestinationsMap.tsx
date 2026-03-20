import { useMemo, useRef, useState } from 'react'
import Map, { Marker, Popup, MapRef } from 'react-map-gl/mapbox'
import { faRefresh, faWind } from '@fortawesome/free-solid-svg-icons'
import { usePage } from '@inertiajs/react'
import { Link } from '@inertiajs/react'
import Icon from '@/Components/Common/Icon'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import 'mapbox-gl/dist/mapbox-gl.css'

interface SpotGuide {
    id: number
    title: string
    slug: string
    latitude: number | null
    longitude: number | null
    country: { name: string; slug: string; continent: string } | null
    thumbnail: string
}

interface Props {
    spotGuides: SpotGuide[]
}

const INITIAL_VIEW = {
    latitude: 10,
    longitude: 15,
    zoom: 1.2,
}

const DestinationsMap = ({ spotGuides }: Props) => {
    const { mapboxToken } = usePage<{ mapboxToken: string }>().props as any
    const mapRef = useRef<MapRef | null>(null)
    const [popupInfo, setPopupInfo] = useState<SpotGuide | null>(null)

    const markers = useMemo(
        () =>
            spotGuides
                .filter((s) => s.latitude && s.longitude)
                .map((spot) => (
                    <Marker
                        key={spot.id}
                        longitude={spot.longitude!}
                        latitude={spot.latitude!}
                        anchor="center"
                        onClick={(e) => {
                            e.originalEvent.stopPropagation()
                            setPopupInfo(spot)
                        }}
                        className="cursor-pointer"
                    >
                        <span className="size-8 aspect-square rounded-full flex items-center justify-center border border-white bg-primary">
                            <Icon icon={faWind} size="size-4" customClasses="text-white" />
                        </span>
                    </Marker>
                )),
        [spotGuides]
    )

    const handleReset = () => {
        mapRef.current?.flyTo({
            center: [INITIAL_VIEW.longitude, INITIAL_VIEW.latitude],
            zoom: INITIAL_VIEW.zoom,
            duration: 1000,
        })
        setPopupInfo(null)
    }

    return (
        <BlockWrapper>
            <Map
                ref={mapRef}
                mapboxAccessToken={mapboxToken}
                initialViewState={INITIAL_VIEW}
                style={{ height: 500 }}
                mapStyle="mapbox://styles/mapbox/light-v11"
                logoPosition="top-right"
                attributionControl={true}
                onLoad={(e) => {
                    e.target.setFog({
                        color: '#ffffff',
                        'high-color': '#ffffff',
                        'space-color': '#ffffff',
                        'horizon-blend': 0,
                    })
                }}
            >
                {markers}

                {popupInfo && popupInfo.longitude && popupInfo.latitude && (
                    <Popup
                        longitude={popupInfo.longitude}
                        latitude={popupInfo.latitude}
                        anchor="left"
                        onClose={() => setPopupInfo(null)}
                        closeOnClick={false}
                    >
                        <Link
                            href={`/destinations/${popupInfo.slug}`}
                            className="block text-sm font-semibold text-primary hover:underline"
                        >
                            {popupInfo.title}
                        </Link>
                        {popupInfo.country && (
                            <p className="text-xs text-gray-500">{popupInfo.country.name}</p>
                        )}
                    </Popup>
                )}

                <button
                    className="absolute left-4 bottom-4 flex items-center gap-x-2 bg-white px-3 py-2 rounded-sm shadow-md"
                    onClick={handleReset}
                >
                    <Icon icon={faRefresh} size="size-4" />
                    <span className="text-sm">Reset</span>
                </button>
            </Map>
        </BlockWrapper>
    )
}

export default DestinationsMap
