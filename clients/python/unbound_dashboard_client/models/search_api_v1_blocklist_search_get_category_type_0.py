from enum import Enum


class SearchApiV1BlocklistSearchGetCategoryType0(str, Enum):
    JUDICIAL = "Judicial"
    MALWAREADWARE = "Malware/Adware"
    PHISHING = "Phishing"

    def __str__(self) -> str:
        return str(self.value)
