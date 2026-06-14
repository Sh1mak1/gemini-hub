import TourismSpotDescription from '@/Components/TourismTest/TourismSpotDescription';
import TourismSpotDistance from '@/Components/TourismTest/TourismSpotDistance';
import TourismSpotImage from '@/Components/TourismTest/TourismSpotImage';
import TourismSpotName from '@/Components/TourismTest/TourismSpotName';

export default function TourismSpotCard({ spot, locationName, index }) {
    return (
        <article className="group overflow-hidden rounded-[1.75rem] border border-[#e2d4bc] bg-[#fffdf8] shadow-[0_18px_40px_-28px_rgba(62,44,28,0.55)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_48px_-24px_rgba(62,44,28,0.45)]">
            <TourismSpotImage imageUrl={spot.image_url} name={spot.name} />
            <div className="space-y-3 p-5 sm:p-6">
                <TourismSpotName name={spot.name} index={index} />
                <TourismSpotDistance
                    distanceText={spot.distance_text}
                    distanceKm={spot.distance_km}
                    locationName={locationName}
                />
                <TourismSpotDescription description={spot.description} />
            </div>
        </article>
    );
}
