class Constants {
  static const String passwordRegex =
      r'^(?=.*\d{1})(?=.*[A-Za-z\u0621-\u064A]{1})(?=.*[!@#$%^&*{|}?~_=+.\-]{0})(?=.*[a-zA-Z\u0621-\u064A0-9@$!%*?&{|}~_=+.\-])(?!.*\s).{6,100}$';

  static const double kMiniPlayerHeight = 55.0;
  static double bottomNavigationBarHeight = 56.0;
  // MediaType
  static String mediaTypeSongs = "0";
  static String mediaTypeSingle = "4";
  static String mediaTypeVideos = "1";
  static String mediaTypeAudioEpisodes = "9";
  static String mediaTypeVideoEpisodes = "10";

  // Order
  static String orderTypeDescending = "0";
  static String orderTypeAscending = "1";

  // Media Filter Types
  static String mediaFilterLatest = "0";
  static String mediaFilterTypeAlphabet = "1";
  static String mediaFilterTypeDownloadCount = "2";
  static String mediaFilterTypePrice = "3";
  static String mediaFilterTypeViewCount = "5";
  static String mediaFilterTypeRating = "6";
  static String mediaFilterPlayCount = "7";
  static String mediaFilter70S = "8";
  static String mediaFilter80S = "9";
  static String mediaFilter90S = "10";

  // Album Filter Types
  static String albumFilterTypeReleaseYear = "1";
  static String albumFilterTypeAlpha = "3";
  static String albumFilterTypeLikesCount = "4";
  static String albumFilterTypeViewsCount = "6";

  // Artist Filter Types
  static String artistFilterTypeAlpha = "1";
  static String artistFilterTypeFollowersCount = "2";
  static String artistFilterTypeNewContent = "3";
  static String artistFilterTypeDownloadsCount = "4";
  static String artistFilterTypeViewCount = "5";

  // Playlists List Filter Types
  static String playlistFilterTypePlayCount = "1";
  static String playlistFilterTypeViewsCount = "5";
  static String playlistFilterTypeLatest = "2";
  static String playlistFilterTypeFollowersCount = "3";

  // Radios List Filter Types
  static String radioFilterPlayCount = "1";
  static String radioFilterAlpha = "2";
  static String radioFilterLikeCount = "3";

  // Podcasts List Filter Types
  static String podcastFilterTypeViewsCount = "1";
  static String podcastFilterTypeLatest = "2";
  static String podcastFilterTypeFollowers = "3";

  // Data Size Limit
  static const String smallDataLimit = "5";
  static const String dataLimit = "10";
  static String dataLimitArtists = "15";
  static String dataLimitGallery = "12";
  static String dataLimitArtistGallery = "18";
  static String galleryType = "1";

  // stores links
  static String googlePlayStoreLink = "https://play.google.com/store/apps/details?id=site.rand.doremi.prod";
  static String appStoreLink = "https://play.google.com/store/apps/details?id=site.rand.doremi.prod";
}
